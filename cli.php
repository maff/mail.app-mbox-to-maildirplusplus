#!/usr/bin/php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConvertCommand extends Command
{
    /**
     * @var string
     */
    protected $source;

    /**
     * @var string
     */
    protected $destination;

    /**
     * @var Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * @var Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    protected function configure()
    {
        $this
            ->setName('convert:mbox')
            ->setDescription('Convert Apple mbox export to Maildir++ layout (see Dovecot wiki)')
            ->addArgument('source', InputArgument::REQUIRED, 'Source directory')
            ->addArgument('destination', InputArgument::REQUIRED, 'Destination directory')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input  = $input;
        $this->output = $output;

        try {
            $this->source      = $this->getPath('source');
            $this->destination = $this->getPath('destination');

            $this->output->writeln(sprintf("Source:\t\t<info>%s</info>", $this->source));
            $this->output->writeln(sprintf("Destination:\t<info>%s</info>", $this->destination));

            $this->processDirectory($this->source);

        } catch (\Exception $e) {
            throw $e;
            $this->output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
        }
    }

    protected function getPath($arg)
    {
       $path = $this->input->getArgument($arg);
       $path = realpath($path);

       if (!(file_exists($path) && is_dir($path))) {
           throw new IOException(sprintf('Invalid %s path', $arg));
       }

       return $path;
    }

    protected function isMboxDir($path)
    {
        return (substr($path, strlen($path) - 5) == '.mbox');
    }

    protected function getRelativeSourcePath($dir)
    {
        return trim(str_replace($this->source, '', $dir), '/');
    }

    protected function processDirectory($dir)
    {
        $relativePath = $this->getRelativeSourcePath($dir);

        if (!empty($relativePath)) {
            $this->output->writeln(sprintf("Processing directory <info>%s</info>", $relativePath));
        } else {
            $this->output->writeln(sprintf("Processing <info>root</info> directory"));
        }

        $contents = scandir($dir);
        foreach ($contents as $fsEntry) {
            if (!in_array($fsEntry, array('.', '..'))) {
                $path = realpath(implode(DIRECTORY_SEPARATOR, array($dir, $fsEntry)));
                if (is_dir($path)) {
                    if ($this->isMboxDir($path)) {
                        $this->output->writeln(sprintf('Found mbox dir <info>%s</info>', $this->getRelativeSourcePath($path)));

                        $stack = array();
                        if ($relativePath !== "") {
                            $stack = explode(DIRECTORY_SEPARATOR, $relativePath);
                        }

                        $stack[] = str_replace('.mbox', '', basename($path));

                        foreach ($stack as &$stackElem) {
                            $stackElem = str_replace(' ', '_', $stackElem);
                            $stackElem = str_replace('.', '-', $stackElem);
                        }

                        $destMbox = str_replace(' ', '_', '.' . implode('.', $stack));

                        $this->output->writeln(sprintf('Creating mbox file for <info>%s</info> at <info>%s</info>', implode(DIRECTORY_SEPARATOR, $stack), $destMbox));

                        $sourceFile = realpath(implode(DIRECTORY_SEPARATOR, array($path, 'mbox')));
                        $destFile   = implode(DIRECTORY_SEPARATOR, array($this->destination, $destMbox));

                        $this->output->writeln(sprintf('cp <info>%s</info> <info>%s</info>', $sourceFile, $destFile));
                        copy($sourceFile, $destFile);
                    } else {
                        $this->output->writeln(sprintf('Found regular dir <info>%s</info>', $this->getRelativeSourcePath($path)));
                        $this->processDirectory($path);
                    }

                    $this->output->writeln('');
                }
            }
        }
    }
}

$application = new Application();
$application->add(new ConvertCommand());
$application->run();
