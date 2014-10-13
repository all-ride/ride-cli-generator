<?php

namespace ride\cli\command\generator;

use ride\library\config\parser\JsonParser;
use ride\library\system\file\browser\FileBrowser;
use ride\library\system\file\File;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\ParameterGenerator;

class GenerateFormCommand extends AbstractClassGeneratorCommand {

    /**
     * @var \ride\library\system\file\browser\FileBrowser
     */
    private $fileBrowser;

    /**
     * @var \ride\library\config\parser\JsonParser
     */
    private $jsonParser;

    public function __construct(FileBrowser $fileBrowser, JsonParser $jsonParser) {
        parent::__construct('generate form');

        $this->fileBrowser = $fileBrowser;
        $this->jsonParser = $jsonParser;
    }

    /**
     * {@inheritdoc}
     */
    public function execute() {
        $definition = array(
            'namespace' => $namespace = $this->defineNamespace(),
            'class'     => array(
                'names' => $formNames = $this->defineFormName($namespace),
            ),
            'dataType'  => $this->ask($this->output, 'Enter form data type: '),
            'rows'      => $this->defineRows($formNames),
            'module'    => $moduleDir = $this->defineModule($this->fileBrowser),
        );

        $this->createFormClass($moduleDir, $definition);
    }

    private function defineFormName($namespace, $question = 'Enter form name: ') {
        return $this->askAndValidate($this->output, $question, function ($shortName) use ($namespace) {
            if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $shortName)) {
                throw new \InvalidArgumentException('Form name is invalid.');
            }

            if (false !== strpos($shortName, 'Form')) {
                throw new \InvalidArgumentException('Form name should not include "Form".');
            }

            $shortName = ucfirst($shortName);
            $longName  = "{$shortName}Form";
            $nsName    = "$namespace\\$longName";

            if (class_exists($nsName)) {
                throw new \InvalidArgumentException(sprintf('Class "%s" already exists.', $nsName));
            }

            return array($shortName, $longName, $nsName);
        });
    }

    private function createFormClass(File $moduleDir, $definition) {
        $classGenerator = new ClassGenerator($definition['class']['names'][1], $definition['namespace']);
        $classGenerator->addUse('ride\\library\\form\\component\\AbstractComponent');
        $classGenerator->addUse('ride\\library\\form\\FormBuilder');
        $classGenerator->addUse('ride\\library\\i18n\\translator\Translator');
        $classGenerator->setExtendedClass('AbstractComponent');

        $prepareFormBody = array(
            '/** @var Translator $translator */',
            '$translator = $options["translator"];',
        );

        foreach ($definition['rows'] as $row) {
            $prepareFormBody[] = sprintf(
                '$formBuilder->addRow("%s", "%s", array(' .
                '"label" => $translator->translate("%s"))' .
                ');', $row['name'], $row['type'], $row['label']
            );
        }

        $classGenerator->addMethodFromGenerator($methodGenerator = (new MethodGenerator('prepareForm'))
            ->setParameter(new ParameterGenerator('formBuilder', 'FormBuilder'))
            ->setParameter(new ParameterGenerator('options', 'array'))
            ->setBody(implode(PHP_EOL, $prepareFormBody))
        );

        $formFile = $moduleDir->getChild('src')
            ->getChild(str_replace('\\', DIRECTORY_SEPARATOR, $definition['class']['names'][2]) . '.php');

        $formFile->write("<?php\n\n" . $classGenerator->generate());

        $this->updateDependencies($this->jsonParser, $moduleDir, $definition['class']['names'][2]);
    }

    private function defineRows(array $formNames, $question = 'Select type: ') {
        $rows = array();

        $choices = array(
            'collection', 'component', 'option', 'select', 'object', 'date', 'time', 'number', 'string', 'text',
            'wysiwyg', 'file', 'image', 'email', 'website', 'password', 'hidden', 'label', 'button'
        );

        while (null !== $selectedIndex = $this->select($this->output, $question, $choices)) {
            $row = array();

            $row['type']  = $choices[$selectedIndex];
            $row['name']  = $this->ask($this->output, 'Enter type name: ', $row['type']);
            $row['label'] = 'label.' . $row['type'] . '.' . strtolower($formNames[0]) . '.' . $row['name'];

            $rows[] = $row;
        }

        return $rows;
    }

}