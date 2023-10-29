<?php
declare(strict_types=1);

namespace Dux\Package;

use Dux\Handlers\Exception;
use GuzzleHttp\Client;
use Nette\Utils\FileSystem;
use Noodlehaus\Parser\Yaml;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Translation\Loader\YamlFileLoader;

class TransYamlCommand extends Command
{

    protected static $defaultName = 'trans:yaml';
    protected static $defaultDescription = 'Automatic translation language pack';

    protected function configure(): void
    {
        $this->addArgument(
            'name',
            InputArgument::REQUIRED,
            'please enter the app name'
        )->addOption('file', null, InputOption::VALUE_REQUIRED, 'The target file.');
    }


    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $file = $input->getOption('file');
        $io = new SymfonyStyle($input, $output);

        $langFile = app_path(ucfirst($name)) . '/Langs/' . $file . '.yaml';
        if (!is_file($langFile)) {
            $io->error('File does not exist');
            return Command::FAILURE;
        }

        $yaml = new Yaml();
        $data = $yaml->parseFile($langFile);

        $helper = $this->getHelper('question');
        $question = new Question('Please enter username: ');
        $username = $helper->ask($input, $output, $question);
        if (!$username) {
            $io->error('Username not entered');
            return Command::FAILURE;
        }

        $question = new Question('Please enter password: ');
        $question->setHidden(true);
        $question->setHiddenFallback(false);
        $password = $helper->ask($input, $output, $question);
        if (!$password) {
            $io->error('password not entered');
            return Command::FAILURE;
        }

        [$langName, $lang] = explode('.', $file, 2);

        $result = [];
        $this->extractLeafNodes($data, $result);
        $resultStr = implode("\n", $result);

        $client = new Client();
        try {
            $response = $client->post(Package::$url . '/v/services/trans', [
                'headers' => [
                    'Accept' => 'application/json'
                ],
                'auth' => [$username, $password],
                'json' => [
                    'content' => $resultStr,
                    'lang' => $lang,
                ]
            ]);
            $content = $response->getBody()?->getContents();
        }catch (\Exception $e) {
            $response = $e->getResponse();
            $content = $response->getBody()?->getContents();
        }
        if ($response->getStatusCode() == 401) {
            $io->error('[CLOUD] Wrong username and password');
            return Command::FAILURE;
        }
        $responseData = json_decode($content ?: '', true);
        if (!$responseData || $response->getStatusCode() !== 200) {
            $io->warning('[CLOUD] ' . $response->getStatusCode() . ' ' . ($responseData['message'] ?: 'Server connection failed'));
            return Command::FAILURE;
        }

        $result = json_decode($content, true);
        if ($result['data']) {
            throw new Exception('Translation request failed');
        }

        $langContent = file_get_contents($langFile);


        foreach ($result['data'] as $key => $vo) {
            $content = $langContent;
            foreach ($vo as $item) {
                $content = str_replace($item['src'], $item['dst'], $content);
            }
            $file = app_path(ucfirst($name)) . '/Langs/' . $langName . '.' . $key . '.yaml';
            file_put_contents($file, $content);
        }


        $io->success('Trans Success');
        return Command::SUCCESS;
    }

    private function extractLeafNodes($array, &$result = []): void
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $this->extractLeafNodes($value, $result);
            } else {
                $result[] = $value;
            }
        }
    }

}