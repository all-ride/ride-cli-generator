<?php

namespace ride\cli\command\generator;

use ride\library\cli\command\extension\AbstractCommand;
use ride\library\config\parser\JsonParser;
use ride\library\system\file\browser\FileBrowser;

class GenerateModuleCommand extends AbstractCommand {

    /**
     * @var \ride\library\system\file\browser\FileBrowser
     */
    private $fileBrowser;

    /**
     * @var \ride\library\config\parser\JsonParser
     */
    private $jsonParser;

    public function __construct(FileBrowser $fileBrowser, JsonParser $jsonParser) {
        $this->fileBrowser = $fileBrowser;
        $this->jsonParser  = $jsonParser;

        parent::__construct('generate module');
    }

    /**
     * {@inheritdoc}
     */
    public function execute() {
        $moduleName = $this->askAndValidate($this->output, 'Enter module name: ', function ($moduleName) {
            if (!preg_match('/^ride-(lib|cli|web|app)-/', $moduleName)) {
                throw new \InvalidArgumentException('Invalid module name. Module name should start with ride-(lib|cli|web|app).');
            }

            return $moduleName;
        });

        $moduleDir = $this->fileBrowser->getApplicationDirectory()
            ->getParent()
            ->getChild('modules')
            ->getChild($moduleName);
        $moduleDir->getChild('ride.json')->write($this->jsonParser->parseFromPhp(array()));

        $configDir = $moduleDir->getChild('config');

        $configDir->getChild('dependencies.json')
            ->write($this->jsonParser->parseFromPhp(array('dependencies' => array())));

        $configDir->getChild('routes.json')
            ->write($this->jsonParser->parseFromPhp(array('routes' => array())));

        $lastChild = $moduleDir->getChild('src');
        foreach (explode('-', str_replace(array('lib', 'app'), array('library', 'application'), $moduleName)) as $i => $part) {
            $lastChild = $lastChild->getChild($part);
        }

        $lastChild->create();

        $this->output->writeLine('Module structure generated.');
    }

}