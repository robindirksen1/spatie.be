<?php

namespace App\Console\Commands;

use App\Support\ValueStores\UpdatedRepositoriesValueStore;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use React\ChildProcess\Process;
use React\EventLoop\Factory;
use React\EventLoop\StreamSelectLoop;
use function React\Promise\all;
use Spatie\Sheets\Sheets;
use function WyriHaximus\React\childProcessPromise;

class ImportDocsFromRepositoriesCommand extends Command
{
    protected $signature = 'docs:import {--repo=} {--all}';

    protected $description = 'Fetches docs from all repositories in docs-repositories.json';

    public function handle()
    {
        $this->info('Importing docs...');

        $loop = Factory::create();

        $updatedRepositoriesValueStore = UpdatedRepositoriesValueStore::make();

        $updatedRepositoryNames = $updatedRepositoriesValueStore->getNames();

        if ($extraRepo = $this->option('repo')) {
            $updatedRepositoryNames[] = $extraRepo;
        }

        if ($this->option('all')) {
            $updatedRepositoryNames = collect(config('docs.repositories'))
                ->map(function (array $repository) {
                    return $repository['repository'];
                })
                ->toArray();
        }

        $this
            ->convertRepositoriesToProcesses($updatedRepositoryNames, $loop)
            ->pipe(fn (Collection $processes) => $this->wrapInPromise($processes));

        $loop->run();

        $updatedRepositoriesValueStore->flush();

        $this->info('All done!');
    }

    protected function convertRepositoriesToProcesses(
        array $updatedRepositoryNames,
        StreamSelectLoop $loop
    ): Collection {
        $repositoriesWithDocs = $this->getRepositoriesWithDocs();

        return collect($updatedRepositoryNames)
            ->map(fn (string $repositoryName) => $repositoriesWithDocs[$repositoryName] ?? null)
            ->filter()
            ->flatMap(function (array $repository) {
                return collect($repository['branches'])
                    ->map(fn (string $alias, string $branch) => [$repository, $alias, $branch])
                    ->values()
                    ->toArray();
            })
            ->mapSpread(function (array $repository, string $alias, string $branch) use ($loop) {
                $process = $this->createProcessComponent($repository, $branch, $alias);

                $this->info("Created import process for {$repository['name']} {$branch}");

                return childProcessPromise($loop, $process);
            });
    }

    protected function wrapInPromise(Collection $processes): void
    {
        all($processes->toArray())
            ->then(function (): void {
                $this->info('Fetched docs from all repositories.');

                $this->info('Caching Sheets.');

                $pages = app(Sheets::class)->collection('docs')->all()->sortBy('weight');

                cache()->store('docs')->forever('docs', $pages);

                $this->info('Done caching Sheets.');
            }, function (Exception $exception) {
                report($exception);

                throw $exception;
            })
            ->always(function (): void {
                File::deleteDirectory(storage_path('docs-temp/'));
            });
    }

    protected function getRepositoriesWithDocs(): Collection
    {
        return collect(config('docs.repositories'))->keyBy('repository');
    }

    protected function createProcessComponent(array $repository, string $branch, string $alias): Process
    {
        $accessToken = config('services.github.docs_access_token');
        $publicDocsAssetPath = public_path('docs');

        return new Process(
            <<<BASH
                    rm -rf storage/docs/{$repository['name']}/{$alias} \
                    && mkdir -p storage/docs/{$repository['name']}/{$alias} \
                    && mkdir -p storage/docs-temp/{$repository['name']}/{$alias} \
                    && cd storage/docs-temp/{$repository['name']}/{$alias} \
                    && git init \
                    && git config core.sparseCheckout true \
                    && echo "/docs" >> .git/info/sparse-checkout \
                    && git remote add -f origin https://{$accessToken}@github.com/spatie/{$repository['name']}.git \
                    && git pull origin ${branch} \
                    && cp -r docs/* ../../../docs/{$repository['name']}/{$alias} \
                    && echo "---\ntitle: {$repository['name']}\ncategory: {$repository['category']}\n---" > ../../../docs/{$repository['name']}/_index.md \
                    && cd docs/ \
                    && find . -not -name '*.md' | cpio -pdm {$publicDocsAssetPath}/{$repository['name']}/{$alias}/
                BASH
        );
    }
}
