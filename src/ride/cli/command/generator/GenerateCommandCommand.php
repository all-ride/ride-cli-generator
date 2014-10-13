<?php

namespace ride\cli\command\generator;

use ride\library\system\file\browser\FileBrowser;
use ride\library\system\file\File;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\MethodGenerator;

class GenerateCommandCommand extends AbstractClassGeneratorCommand
{

    /**
     * @var \ride\library\system\file\browser\FileBrowser
     */
    private $fileBrowser;

    public function __construct(FileBrowser $fileBrowser) {
        parent::__construct('generate command');
        $this->fileBrowser = $fileBrowser;
    }

    public function execute() {
        $definition = array(
            'namespace' => $namespace = $this->defineNamespace(),
            'class' => array(
                'names' => $this->defineCommandName($namespace),
            ),
            'module' => $moduleDir = $this->defineModule($this->fileBrowser),
        );

        $this->createCommandClass($moduleDir, $definition);
    }

    private function createCommandClass(File $moduleDir, $definition) {
        $classGenerator = new ClassGenerator($definition['class']['names'][1], $definition['namespace']);
        $classGenerator->addUse('ride\\library\\cli\\command\\extension\\AbstractCommand');
        $classGenerator->setExtendedClass('AbstractCommand');
        $classGenerator->addMethod('execute');

        $formFile = $moduleDir->getChild('src')
            ->getChild(str_replace('\\', DIRECTORY_SEPARATOR, $definition['class']['names'][2]) . '.php');

        $formFile->write("<?php\n\n" . $classGenerator->generate());

        $this->updateDependencies($this->jsonParser, $moduleDir, $definition['class']['names'][2]);
    }

    private function defineCommandName($namespace, $question = 'Enter command name: ') {
        return $this->askAndValidate($this->output, $question, function ($shortName) use ($namespace) {
            if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $shortName)) {
                throw new \InvalidArgumentException('Command name is invalid.');
            }

            if (false !== strpos($shortName, 'Command')) {
                throw new \InvalidArgumentException('Command name should not include "Command".');
            }

            $shortName = ucfirst($shortName);
            $longName  = "{$shortName}Command";
            $nsName    = "$namespace\\$longName";

            if (class_exists($nsName)) {
                throw new \InvalidArgumentException(sprintf('Class "%s" already exists.', $nsName));
            }

            return array($shortName, $longName, $nsName);
        });
    }
}