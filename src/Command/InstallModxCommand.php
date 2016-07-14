<?php
namespace modmore\Gitify\Command;

use modmore\Gitify\BaseCommand;
use modmore\Gitify\Mixins\DownloadModx;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Class BuildCommand
 *
 * Installs a clean version of MODX.
 *
 * @package modmore\Gitify\Command
 */
class InstallModxCommand extends BaseCommand
{
    use DownloadModx;

    public $loadConfig = false;
    public $loadMODX = false;

    protected function configure()
    {
        $this
            ->setName('modx:install')
            ->setAliases(array('install:modx'))
            ->setDescription('Downloads, configures and installs a fresh MODX installation. [Note: <info>install:modx</info> will be removed in 1.0, use <info>modx:install</info> instead]')
            ->addArgument(
                'version',
                InputArgument::OPTIONAL,
                'The version of MODX to install, in the format 2.3.2-pl. Leave empty or specify "latest" to install the last stable release.',
                'latest'
            )
            ->addOption(
                'download',
                'd',
                InputOption::VALUE_NONE,
                'Force download the MODX package even if it already exists in the cache folder.'
            )
            ->addOption(
                'advanced',
                'a',
                InputOption::VALUE_NONE,
                'Download advanced type of installation (with choose of CORE_PATH and etc.).'
            );
    }

    /**
     * Runs the command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $version = $this->input->getArgument('version');
        $forced = $this->input->getOption('download');
        $advanced = $this->input->getOption('advanced');

        if (!$this->getMODX($version, $forced, $advanced)) {
            return 1; // exit
        }

        // Create the XML config
        $config = $this->createMODXConfig($advanced);

        // Variables for running the setup
        $tz = date_default_timezone_get();
        $wd = GITIFY_WORKING_DIR;
        $output->writeln("Running MODX Setup...");

        // Actually run the CLI setup
        exec("php -d date.timezone={$tz} {$wd}setup/index.php --installmode=new --config={$config['path']} --core_path={$config['core']}", $setupOutput);
        $output->writeln("<comment>{$setupOutput[0]}</comment>");

        // Try to clean up the config file
        if (!unlink($config)) {
            $output->writeln("<warning>Warning:: could not clean up the setup config file, please remove this manually.</warning>");
        }

        $output->writeln('Done! ' . $this->getRunStats());
        return 0;
    }

    /**
     * Asks the user to complete a bunch of details and creates a MODX CLI config xml file
     *
     * @param bool $advanced
     */
    protected function createMODXConfig($advanced = false)
    {
        $directory = GITIFY_WORKING_DIR;

        // Creating config xml to install MODX with
        $this->output->writeln("Please complete following details to install MODX. Leave empty to use the [default].");

        $helper = $this->getHelper('question');
        $dbType = 'mysql';
        $dbHost = 'localhost';
        $dbUser = 'root';
        $dbPass = '';
        $dbConnectionCharset = 'utf8';
        $dbCharset = 'utf8';
        $dbCollation = 'utf8_general_ci';
        $dbTablePrefix = 'modx_';
        $assetsUrl = 'assets';
        $corePath = $directory . 'core/';
        $managerUrl = 'manager';
        $connectorsUrl = 'connectors';

        if ($advanced) {
            $question = new ChoiceQuestion(
                'Select MODX database type [mysql]: ',
                array('mysql', 'sqlsrv'),
                0
            );
            $question->setErrorMessage('MODX don\'t support %s database.');
            $dbType = $helper->ask($this->input, $this->output, $question);


            $question = new Question("Database host [{$dbHost}]: ", $dbHost);
            $dbHost = $helper->ask($this->input, $this->output, $question);

            $question = new ChoiceQuestion(
                'Database connection charset [utf8]: ',
                array('armscii8', 'ascii'  , 'big5'  , 'binary' , 'cp1250', 'cp1251'  ,
                      'cp1256'  , 'cp1257' , 'cp850' , 'cp852'  , 'cp866' , 'cp932'   ,
                      'dec8'    , 'eucjpms', 'euckr' , 'gb2312' , 'gbk'   , 'geostd8' ,
                      'greek'   , 'hebrew' , 'hp8'   , 'keybcs2', 'koi8r' , 'koi8u'   ,
                      'latin1'  , 'latin2' , 'latin5', 'latin7' , 'macce' , 'macroman',
                      'sjis'    , 'swe7'   , 'tis620', 'ucs2'   , 'ujis'  , 'utf16'   ,
                      'utf32'   , 'utf8'   , 'utf8mb4'),
                37);
            $question->setErrorMessage("MODX doesn't support %s as connection charset.");
            $dbConnectionCharset = $helper->ask($this->input, $this->output, $question);

            $question = new Question("Database charset [{$dbConnectionCharset}]: ", $dbConnectionCharset);
            $dbCharset = $helper->ask($this->input, $this->output, $question);

            $dbCollationArray = array(
                'armscii8_bin'            , 'armscii8_general_ci', 'ascii_bin'            ,
                'ascii_general_ci'        , 'big5_bin'           , 'big5_chinese_ci'      ,
                'binary'                  , 'cp1250_bin'         , 'cp1250_croatian_ci'   ,
                'cp1250_czech_cs'         , 'cp1250_general_ci'  , 'cp1250_polish_ci'     ,
                'cp1251_bin'              , 'cp1251_bulgarian_ci', 'cp1251_general_ci'    ,
                'cp1251_general_cs'       , 'cp1251_ukrainian_ci', 'cp1256_bin'           ,
                'cp1256_general_ci'       , 'cp1257_bin'         , 'cp1257_general_ci'    ,
                'cp1257_lithuanian_ci'    , 'cp850_bin'          , 'cp850_general_ci'     ,
                'cp852_bin'               , 'cp852_general_ci'   , 'cp866_bin'            ,
                'cp866_general_ci'        , 'cp932_bin'          , 'cp932_japanese_ci'    ,
                'dec8_bin'                , 'dec8_swedish_ci'    , 'eucjpms_bin'          ,
                'eucjpms_japanese_ci'     , 'euckr_bin'          , 'euckr_korean_ci'      ,
                'gb2312_bin'              , 'gb2312_chinese_ci'  , 'gbk_bin'              ,
                'gbk_chinese_ci'          , 'geostd8_bin'        , 'geostd8_general_ci'   ,
                'greek_bin'               , 'greek_general_ci'   , 'hebrew_bin'           ,
                'hebrew_general_ci'       , 'hp8_bin'            , 'hp8_english_ci'       ,
                'keybcs2_bin'             , 'keybcs2_general_ci' , 'koi8r_bin'            ,
                'koi8r_general_ci'        , 'koi8u_bin'          , 'koi8u_general_ci'     ,
                'latin1_bin'              , 'latin1_danish_ci'   , 'latin1_general_ci'    ,
                'latin1_general_cs'       , 'latin1_german1_ci'  , 'latin1_german2_ci'    ,
                'latin1_spanish_ci'       , 'latin1_swedish_ci'  , 'latin2_bin'           ,
                'latin2_croatian_ci'      , 'latin2_czech_cs'    , 'latin2_general_ci'    ,
                'latin2_hungarian_ci'     , 'latin5_bin'         , 'latin5_turkish_ci'    ,
                'latin7_bin'              , 'latin7_estonian_cs' , 'latin7_general_ci'    ,
                'latin7_general_cs'       , 'macce_bin'          , 'macce_general_ci'     ,
                'macroman_bin'            , 'macroman_general_ci', 'sjis_bin'             ,
                'sjis_japanese_ci'        , 'swe7_bin'           , 'swe7_swedish_ci'      ,
                'tis620_bin'              , 'tis620_thai_ci'     , 'ucs2_bin'             ,
                'ucs2_czech_ci'           , 'ucs2_danish_ci'     , 'ucs2_esperanto_ci'    ,
                'ucs2_estonian_ci'        , 'ucs2_general50_ci'  , 'ucs2_general_ci'      ,
                'ucs2_hungarian_ci'       , 'ucs2_icelandic_ci'  , 'ucs2_latvian_ci'      ,
                'ucs2_lithuanian_ci'      , 'ucs2_persian_ci'    , 'ucs2_polish_ci'       ,
                'ucs2_roman_ci'           , 'ucs2_romanian_ci'   , 'ucs2_sinhala_ci'      ,
                'ucs2_slovak_ci'          , 'ucs2_slovenian_ci'  , 'ucs2_spanish2_ci'     ,
                'ucs2_spanish_ci'         , 'ucs2_swedish_ci'    , 'ucs2_turkish_ci'      ,
                'ucs2_unicode_ci'         , 'ujis_bin'           , 'ujis_japanese_ci'     ,
                'utf16_bin'               , 'utf16_czech_ci'     , 'utf16_danish_ci'      ,
                'utf16_esperanto_ci'      , 'utf16_estonian_ci'  , 'utf16_general_ci'     ,
                'utf16_hungarian_ci'      , 'utf16_icelandic_ci' , 'utf16_latvian_ci'     ,
                'utf16_lithuanian_ci'     , 'utf16_persian_ci'   , 'utf16_polish_ci'      ,
                'utf16_roman_ci'          , 'utf16_romanian_ci'  , 'utf16_sinhala_ci'     ,
                'utf16_slovak_ci'         , 'utf16_slovenian_ci' , 'utf16_spanish2_ci'    ,
                'utf16_spanish_ci'        , 'utf16_swedish_ci'   , 'utf16_turkish_ci'     ,
                'utf16_unicode_ci'        , 'utf32_bin'          , 'utf32_czech_ci'       ,
                'utf32_danish_ci'         , 'utf32_esperanto_ci' , 'utf32_estonian_ci'    ,
                'utf32_general_ci'        , 'utf32_hungarian_ci' , 'utf32_icelandic_ci'   ,
                'utf32_latvian_ci'        , 'utf32_lithuanian_ci', 'utf32_persian_ci'     ,
                'utf32_polish_ci'         , 'utf32_roman_ci'     , 'utf32_romanian_ci'    ,
                'utf32_sinhala_ci'        , 'utf32_slovak_ci'    , 'utf32_slovenian_ci'   ,
                'utf32_spanish2_ci'       , 'utf32_spanish_ci'   , 'utf32_swedish_ci'     ,
                'utf32_turkish_ci'        , 'utf32_unicode_ci'   , 'utf8_bin'             ,
                'utf8_czech_ci'           , 'utf8_danish_ci'     , 'utf8_esperanto_ci'    ,
                'utf8_estonian_ci'        , 'utf8_general50_ci'  , 'utf8_general_ci'      ,
                'utf8_general_mysql500_ci', 'utf8_hungarian_ci'  , 'utf8_icelandic_ci'    ,
                'utf8_latvian_ci'         , 'utf8_lithuanian_ci' , 'utf8_persian_ci'      ,
                'utf8_polish_ci'          , 'utf8_roman_ci'      , 'utf8_romanian_ci'     ,
                'utf8_sinhala_ci'         , 'utf8_slovak_ci'     , 'utf8_slovenian_ci'    ,
                'utf8_spanish2_ci'        , 'utf8_spanish_ci'    , 'utf8_swedish_ci'      ,
                'utf8_turkish_ci'         , 'utf8_unicode_ci'    , 'utf8mb4_bin'          ,
                'utf8mb4_czech_ci'        , 'utf8mb4_danish_ci'  , 'utf8mb4_esperanto_ci' ,
                'utf8mb4_estonian_ci'     , 'utf8mb4_general_ci' , 'utf8mb4_hungarian_ci' ,
                'utf8mb4_icelandic_ci'    , 'utf8mb4_latvian_ci' , 'utf8mb4_lithuanian_ci',
                'utf8mb4_persian_ci'      , 'utf8mb4_polish_ci'  , 'utf8mb4_roman_ci'     ,
                'utf8mb4_romanian_ci'     , 'utf8mb4_sinhala_ci' , 'utf8mb4_slovak_ci'    ,
                'utf8mb4_slovenian_ci'    , 'utf8mb4_spanish2_ci', 'utf8mb4_spanish_ci'   ,
                'utf8mb4_swedish_ci'      , 'utf8mb4_turkish_ci' , 'utf8mb4_unicode_ci'   );
            $question = new ChoiceQuestion(
                'Database collation [{$dbCollation}]: ',
                preg_grep("/^{$dbCharset}_/", $dbCollationArray),
                158);
            $dbCollation = $helper->ask($this->input, $this->output, $question);

            $question = new Question('Database table prefix [{$dbTablePrefix}]: ', $dbTablePrefix);
            $dbTablePrefix = $helper->ask($this->input, $this->output, $question);

            $question = new Question('Assets URL [{$assetsUrl}]: ', $assetsUrl);
            $assetsUrl = $helper->ask($this->input, $this->output, $question);

            $question = new Question('Manager URL [{$managerUrl}]: ', $managerUrl);
            $managerUrl = $helper->ask($this->input, $this->output, $question);

            $question = new Question('Connectors URL [{$connectorsUrl}]: ', $connectorsUrl);
            $connectorsUrl = $helper->ask($this->input, $this->output, $question);

            $question = new Question('Path to core directory [{$corePath}]: ', $corePath);
            $corePath = $helper->ask($this->input, $this->output, $question);
            if ($directory . 'core/' != $corePath) {
                rename($directory . 'core/', $corePath);
                exec ('find '.$corePath.' -type d -exec chmod 0755 {} +');
                exec ('find '.$corePath.' -type f -exec chmod 0644 {} +');
            }

            $question = new Question('Save config to [{$directory}config.xml]: ', $directory.'.config.xml');
            $configPath = $helper->ask($this->input, $this->output, $question);
        }

        $dbConnection = false;
        do {
            $defaultDbName = basename(GITIFY_WORKING_DIR);
            $question = new Question("Database Name [{$defaultDbName}]: ", $defaultDbName);
            $dbName = $helper->ask($this->input, $this->output, $question);

            $question = new Question('Database User [root]: ', 'root');
            $dbUser = $helper->ask($this->input, $this->output, $question);

            $question = new Question('Database Password: ');
            $question->setHidden(true);
            $dbPass = $helper->ask($this->input, $this->output, $question);

            try {
                $pdoHost = $dbType == 'mysql' ? 'mysql:host' : 'sqlsrv:server';
                $dbConnection = new \PDO("{$pdoHost}={$dbHost}", $dbUser, $dbPass, array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT));
                $this->output->writeln('<info>Database connection success</info>');
            } catch (PDOException $e) {
                $this->output->writeln("<error>Cann't connect to localhost as {$dbUser}: " . $e->getMessage() . "</error>");
                if (!$advanced) {
                    $question = new ConfirmationQuestion('Would you like to activate <info>Advanced Mode Installation</info>? <comment>(Y/n)</comment>', true);
                    if ($helper->ask($input, $output, $question)) {
                        $advanced = true;
                    }
                }
            }
        } while (!$dbConnection);

        $question = new Question('Hostname [' . gethostname() . ']: ', gethostname());
        $host = $helper->ask($this->input, $this->output, $question);
        $host = rtrim(trim($host), '/');

        $defaultBaseUrl = '/';
        $question = new Question('Base URL [{$defaultBaseUrl}]: ', $defaultBaseUrl);
        $baseUrl = $helper->ask($this->input, $this->output, $question);
        $baseUrl = '/' . trim(trim($baseUrl), '/') . '/';
        $baseUrl = str_replace('//', '/', $baseUrl);

        $question = new Question('Manager Language [en]: ', 'en');
        $language = $helper->ask($this->input, $this->output, $question);

        $defaultMgrUser = basename(GITIFY_WORKING_DIR) . '_admin';
        $question = new Question('Manager User [{$defaultMgrUser}]: ', $defaultMgrUser);
        $managerUser = $helper->ask($this->input, $this->output, $question);

        $question = new Question('Manager User Password [generated]: ', 'generate');
        $question->setHidden(true);
        $question->setValidator(function ($value) {
            if (empty($value) || strlen($value) < 8) {
                throw new \RuntimeException(
                    'Please specify a password of at least 8 characters to continue.'
                );
            }

            return $value;
        });
        $managerPass = $helper->ask($this->input, $this->output, $question);

        if ($managerPass == 'generate') {
            $managerPass = substr(str_shuffle(md5(microtime(true))), 0, rand(8, 15));
            $this->output->writeln("<info>Generated Manager Password: {$managerPass}</info>");
        }

        $question = new Question('Manager Email: [{$managerUser}@{$host}]: ', $managerUser . '@' . $host);
        $managerEmail = $helper->ask($this->input, $this->output, $question);

        $configXMLContents = "<modx>
  <database_type>{$dbType}</database_type>
  <database_server>{$dbHost}</database_server>
  <database>{$dbName}</database>
  <database_user>{$dbUser}</database_user>
  <database_password>{$dbPass}</database_password>
  <database_connection_charset>{$dbConnectionCharset}</database_connection_charset>
  <database_charset>{$dbCharset}</database_charset>
  <database_collation>{$dbCollation}</database_collation>
  <table_prefix>{$dbTablePrefix}</table_prefix>
  <https_port>443</https_port>
  <http_host>{$host}</http_host>
  <cache_disabled>1</cache_disabled>
  <inplace>0</inplace>
  <unpacked>0</unpacked>
  <language>{$language}</language>
  <cmsadmin>{$managerUser}</cmsadmin>
  <cmspassword>{$managerPass}</cmspassword>
  <cmsadminemail>{$managerEmail}</cmsadminemail>
  <core_path>{$corePath}</core_path>
  <assets_path>{$directory}{$assetsUrl}/</assets_path>
  <assets_url>{$baseUrl}{$assetsUrl}/</assets_url>
  <context_mgr_path>{$directory}{$managerUrl}/</context_mgr_path>
  <context_mgr_url>{$baseUrl}{$managerUrl}/</context_mgr_url>
  <context_connectors_path>{$directory}{$connectorsUrl}/</context_connectors_path>
  <context_connectors_url>{$baseUrl}{$connectorsUrl}/</context_connectors_url>
  <context_web_path>{$directory}</context_web_path>
  <context_web_url>{$baseUrl}</context_web_url>
  <remove_setup_directory>1</remove_setup_directory>
</modx>";

        file_put_contents($configPath, $configXMLContents);
        return array('path' => $configPath, 'core' => $corePath);
    }

}