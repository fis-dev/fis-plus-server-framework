<?php

define('WWW_ROOT', dirname(__FILE__));

require(WWW_ROOT . '/smarty/Smarty.class.php');
require(WWW_ROOT . '/php-simulation-env/log/Log.class.php');
require(WWW_ROOT . '/php-simulation-env/mock-data/Mock.class.php');
require(WWW_ROOT . '/php-simulation-env/rewrite/Rewrite.class.php');

$rewrite = null;

function init($config) {
    global $rewrite;
    // log init
    Log::getLogger(array(
        'fd' => WWW_ROOT . '/app.log',
        'level' => Log::ALL,
        'requestUrl' => $_SERVER['REQUEST_URI']
    ));

    // mock init
    Mock::init(WWW_ROOT, $config['encoding']);

    // rewrite init
    $rewrite = new Rewrite(WWW_ROOT . '/server-conf', $config['encoding']);
    $rewrite->addRule(Rule::REWRITE, '@/static/.*@', '$&');
    $rewrite->addConfigFile($config['namespace'] . '.conf');
    $rewrite->addConfigFile('common.conf');
    $rewrite->addRule(Rule::REWRITE, '@^/?$@', 'welcome.php');
    $rewrite->dispatch();
}

function routing() {
    $requestUri = $_SERVER['REQUEST_URI'];
    $requestUri = preg_replace('@\?.*$@', '', $requestUri);
    $requestUri = substr($requestUri, 1);
    $uriSplit = explode('/', $requestUri);
    $smartyConfigFile = realpath(WWW_ROOT . '/smarty.conf');
    $config = array(
        'namespace' => $uriSplit[0],
        'encoding' => 'utf-8',
        'smarty' => array(
            'left_delimiter' => '{%',
            'right_delimiter' => '%}',
            'template_dir' => './template',
            'plugins_dir' => array(
                './plugin'
            ),
            'config_dir' => './config'
        ) 
    );
    if ($smartyConfigFile) {
        $smartyConfig = parse_ini_file($smartyConfigFile);
        if (isset($smartyConfig['encoding'])) {
            //ugly, .... @TODO
            $config['encoding'] = $smartyConfig['encoding'];
            unset($smartyConfig['encoding']);
        }

        $config['smarty'] = array_merge($config['smarty'], (array) $smartyConfig);
    }

    init($config);

    $smarty = new Smarty();

    $smarty->setTemplateDir($config['smarty']['template_dir']);
    $smarty->setConfigDir($config['smarty']['config_dir']);
    $smarty->setLeftDelimiter($config['smarty']['left_delimiter']);
    $smarty->setRightDelimiter($config['smarty']['right_delimiter']);

    foreach($config['smarty']['plugins_dir'] as $pluginDir) {
        $smarty->addPluginsDir($pluginDir);
    }
    
    $tpl = $requestUri . '.tpl';
    $smarty->assign((array)Mock::getData($tpl));
    $smarty->display($tpl);
}

routing();
