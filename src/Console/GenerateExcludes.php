<?php

declare(strict_types=1);

namespace Snicco\PhpScoperExcludes\Console;

use PhpParser\ParserFactory;
use Snicco\PhpScoperExcludes\Option;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Snicco\PhpScoperExcludes\ExclusionListGenerator;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function getcwd;
use function is_file;
use function gettype;
use function sprintf;
use function is_array;
use function basename;
use function is_string;
use function array_map;
use function filter_var;
use function is_iterable;
use function array_merge;
use function array_values;
use function iterator_to_array;

use const FILTER_VALIDATE_BOOLEAN;

#[AsCommand(name: 'run')]
final class GenerateExcludes extends Command
{
    protected static $defaultName = 'run';
    private string   $repository_root;
    
    public function __construct(string $repository_root)
    {
        $this->repository_root = $repository_root;
        parent::__construct();
        $this->addOption(
            'json',
            null,
            InputOption::VALUE_NONE,
            'Dump the excludes as json.',
        );
        $this->addOption(
            'exclude-empty',
            null,
            InputOption::VALUE_NONE,
            'Require at least one type of(interface,class,trait,constant,function) to be present in order for the file being dumped.',
        );
		$this->addOption(
			'config',
			null,
			InputOption::VALUE_REQUIRED,
			'The path to a config file.',
		);
		$this->addOption(
			'out',
			null,
			InputOption::VALUE_REQUIRED,
			'The path to the output directory (takes precedent over config file).'
		);
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title("Generating exclusion lists.");
        
        $config = $input->getOption('config') ?? $this->repository_root.'/generate-excludes.inc.php';
        if ( ! is_file($config)) {
            $io->error([
                "Configuration file not found at path [$config].",
                'Please run "vendor/bin/generate-excludes generate-config"',
            ]);
            
            return Command::FAILURE;
        }
        
        $config = require_once $config;
        $files = $config[Option::FILES];
        
        if ( ! is_array($files)) {
            $io->error([
                'Option::Files has to be an array of string|iterable.',
                'Got:'.gettype($files),
            ]);
            return Command::FAILURE;
        }
        
        $io->note("Normalizing files...");
        
        $files = $this->normalizeFiles($files);
        $count = count($files);
        
        if ( ! $count) {
            $io->note('No files found. Aborting...');
            return Command::SUCCESS;
        }
        
        $io->info(
            sprintf(
                '%s %s found. Starting to generate excludes...',
                $count,
                $count > 1 ? 'files' : 'file'
            )
        );

	    $output_dir = $input->getOption('out') ?? $config[Option::OUTPUT_DIR] ?? getcwd();

		/* Try create the directory if it does not exist */
		if ( ! is_dir($output_dir)) {
			mkdir( $output_dir, 0755, true );
		}
        
        $generator = $this->newGenerator($output_dir);
        
        $progress_bar = $this->newProgressBar($output, $count);
        $progress_bar->setMessage(basename($files[0]));
        $progress_bar->start();
        
        $json = filter_var($input->getOption('json'), FILTER_VALIDATE_BOOLEAN);
        $exclude_empty = filter_var($input->getOption('exclude-empty'), FILTER_VALIDATE_BOOLEAN);
        
        foreach ($files as $file) {
            $progress_bar->setMessage(basename($file));
            if ($json) {
                $generator->dumpAsJson($file, !$exclude_empty);
            }
            else {
                $generator->dumpAsPhpArray($file, !$exclude_empty);
            }
            $progress_bar->advance();
        }
        
        $progress_bar->finish();
        
        $io->newLine(2);
        $io->success(
            sprintf(
                "Generated exclusion list for %s %s in directory %s.",
                count($files),
                count($files) > 1 ? 'files' : 'file',
                $output_dir,
            )
        );
        
        return Command::SUCCESS;
    }
    
    private function newGenerator(string $output_dir) :ExclusionListGenerator
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        return new ExclusionListGenerator($parser, $output_dir);
    }
    
    /**
     * @return string[]
     */
    private function normalizeFiles(array $files) :array
    {
        $_files = [];
        
        foreach ($files as $file) {
            if (is_string($file)) {
                $_files = array_merge($_files, [$file]);
                continue;
            }
            
            if (is_iterable($file)) {
                $_files = array_merge($_files, iterator_to_array($file));
            }
        }
        return array_values(array_map(fn($file) => (string) $file, $_files));
    }
    
    private function newProgressBar(OutputInterface $output, int $file_count) :ProgressBar
    {
        $progress_bar = new ProgressBar($output, $file_count);
        $progress_bar->setFormat(
            ' %current%/%max% [%bar%] <info>%percent%%</info> %elapsed:6s% <info>(%message%)</info>'
        );
        $progress_bar->setRedrawFrequency(100);
        $progress_bar->maxSecondsBetweenRedraws(0.2);
        $progress_bar->minSecondsBetweenRedraws(0.2);
        
        return $progress_bar;
    }
    
}