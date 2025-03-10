<?php

/*
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'diff-recipe-versions', description: 'Displays the diff between versions of a recipe')]
class DiffRecipeVersionsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('endpoint', InputArgument::OPTIONAL, 'The Flex endpoint', '')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $packages = [];
        $requires = [];

        while (false !== $package = fgets(\STDIN)) {
            $package = substr($package, 0, -1);

            $versions = scandir($package, \SCANDIR_SORT_NONE);
            usort($versions, 'version_compare');

            if (!$versions = \array_slice($versions, 2)) {
                continue;
            }

            $packages[$package] = $versions;

            $requires[] = escapeshellarg($package.':^'.array_pop($versions));
        }

        if ($endpoint = $input->getArgument('endpoint')) {
            $requires = implode(' ', $requires);
            $endpoint = <<<EOMD

## How to test these changes in your application

 1. Add the Shopware flex endpoint in your `composer.json` to {$endpoint}.

    ```sh
    # When jq is installed
    jq '.extra.symfony.endpoint |= [ "{$endpoint}" ] + .' composer.json > composer.tmp && mv composer.tmp composer.json
    ```
    
    or manually
    
    ```json
    "endpoint": [
        "{$endpoint}",
        "https://raw.githubusercontent.com/shopware/recipes/flex/main/index.json",
        "flex://defaults"
    ]
    # On Unix-like (BSD, Linux and macOS)
    export SYMFONY_ENDPOINT={$endpoint}
    # On Windows
    SET SYMFONY_ENDPOINT={$endpoint}
    ```

 2. Install the package(s) related to this recipe:
    ```sh
    composer req {$requires}
    ```

EOMD;
        }

        $head = <<<EOMD
Thanks for the PR 😍
{$endpoint}
## Diff between recipe versions

In order to help with the review stage, I'm in charge of computing the diff between the various versions of patched recipes.
I'm going keep this comment up to date with any updates of the attached patch.

EOMD;

        foreach ($packages as $package => $versions) {
            $previousVersion = array_shift($versions);

            if (!$versions) {
                continue;
            }

            if (null !== $head) {
                $output->writeln($head);
                $head = null;
            }
            $output->writeln(sprintf("### %s\n", $package));

            foreach ($versions as $version) {
                $process = new Process(['git', 'diff', '--color=never', '--no-index', $package.'/'.$previousVersion, $package.'/'.$version]);
                $process->run(null, ['LC_ALL' =>'C']);

                $output->writeln('<details>');
                $output->writeln(sprintf("<summary>%s <em>vs</em> %s</summary>\n", $previousVersion, $version));
                $output->writeln("```diff\n{$process->getOutput()}```");
                $output->writeln("\n</details>\n");

                $previousVersion = $version;
            }
        }

        if (null !== $head) {
            $output->writeln($head);
        }

        return 0;
    }
}
