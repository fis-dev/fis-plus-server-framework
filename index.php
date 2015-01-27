<?php

define('WWW_ROOT', dirname(__FILE__));

require(WWW_ROOT . '/smarty/Smarty.class.php');
require(WWW_ROOT . '/php-simulation-env/log/Log.class.php');
require(WWW_ROOT . '/php-simulation-env/mock-data/Mock.class.php');
require(WWW_ROOT . '/php-simulation-env/rewrite/Rewrite.class.php');


function initSmarty($config) {
    $smartyConfigFile = realpath(WWW_ROOT . '/smarty.conf');
    if ($smartyConfigFile) {
        $smartyConfig = parse_ini_file($smartyConfigFile);
        if (isset($smartyConfig['encoding'])) {
            //ugly, .... @TODO
            $config['encoding'] = $smartyConfig['encoding'];
            unset($smartyConfig['encoding']);
        }
        $config['smarty'] = array_merge($config['smarty'], (array) $smartyConfig);
    }

    $smarty = new Smarty();

    $smarty->setTemplateDir($config['smarty']['template_dir']);
    $smarty->setConfigDir($config['smarty']['config_dir']);
    $smarty->setLeftDelimiter($config['smarty']['left_delimiter']);
    $smarty->setRightDelimiter($config['smarty']['right_delimiter']);

    foreach($config['smarty']['plugins_dir'] as $pluginDir) {
        $smarty->addPluginsDir($pluginDir);
    }
    return $smarty;
}

class TplRewirteHandle implements RewriteHandle {
    private $_smarty = null;

    public function __construct($smarty) {
        $this->_smarty = $smarty;
    }

    public function process($file) {
        $this->_smarty->assign(Mock::getData($file));
        $this->_smarty->display($file);
    }
}

function init($config, $smarty) {
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
    $rewrite->addRule(Rule::REWRITE, '@/favicon.ico$@', 'static/favicon.ico');

    foreach(glob(WWW_ROOT . '/server-conf/**') as $configFile) {
        $configFile = basename($configFile);
        $rewrite->addConfigFile($configFile);
    }

    $rewrite->addRule(Rule::REWRITE, '@^/?$@', 'welcome.php');

    $rewrite->addRewriteHandle('tpl', new TplRewirteHandle($smarty));

    $rewrite->dispatch();
}

function routing() {
    $requestUri = $_SERVER['REQUEST_URI'];
    $requestUri = preg_replace('@\?.*$@', '', $requestUri);
    $requestUri = substr($requestUri, 1);
    $uriSplit = explode('/', $requestUri);
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

    $smarty = initSmarty($config);

    init($config, $smarty);

    $tpl = $requestUri . '.tpl';
    $smarty->assign((array)Mock::getData($tpl));
    $smarty->display($tpl);
}

routing();
