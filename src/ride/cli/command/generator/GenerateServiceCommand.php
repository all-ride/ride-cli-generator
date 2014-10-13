<?php

namespace ride\cli\command\generator;

use ride\library\config\parser\JsonParser;
use ride\library\system\file\browser\FileBrowser;
use ride\library\system\file\File;
use Zend\Code\Generator\ClassGenerator;

class GenerateServiceCommand extends AbstractClassGeneratorCommand
{

    /**
     * @var \ride\library\system\file\browser\FileBrowser
     */
    private $fileBrowser;

    /**
     * @var \ride\library\config\parser\JsonParser
     */
    private $jsonParser;

    public function __construct(FileBrowser $fileBrowser) {
        parent::__construct('generate service');
        $this->fileBrowser = $fileBrowser;
        $this->jsonParser = new JsonParser();
    }

    public function execute() {
        $definition = array(
            'namespace' => $namespace = $this->defineNamespace(),
            'class' => array(
                'names' => $this->defineServiceName($namespace),
            ),
            'module' => $moduleDir = $this->defineModule($this->fileBrowser),
        );

        $this->createServiceClass($moduleDir, $definition);
    }

    private function createServiceClass(File $moduleDir, $definition) {
        $classGenerator = new ClassGenerator($definition['class']['names'][1], $definition['namespace']);

        $formFile = $moduleDir->getChild('src')
            ->getChild(str_replace('\\', DIRECTORY_SEPARATOR, $definition['class']['names'][2]) . '.php');

        $formFile->write("<?php\n\n" . $classGenerator->generate());

        $this->updateDependencies($this->jsonParser, $moduleDir, $definition['class']['names'][2]);
    }

    private function defineServiceName($namespace, $question = 'Enter service name: ') {
        return $this->askAndValidate($this->output, $question, function ($shortName) use ($namespace) {
            if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $shortName)) {
                throw new \InvalidArgumentException('Service name is invalid.');
            }

            if (false !== strpos($shortName, 'Service')) {
                throw new \InvalidArgumentException('Service name should not include "Service".');
            }

            $shortName = ucfirst($shortName);
            $longName  = "{$shortName}Service";
            $nsName    = "$namespace\\$longName";

            if (class_exists($nsName)) {
                throw new \InvalidArgumentException(sprintf('Class "%s" already exists.', $nsName));
            }

            return array($shortName, $longName, $nsName);
        });
    }
}