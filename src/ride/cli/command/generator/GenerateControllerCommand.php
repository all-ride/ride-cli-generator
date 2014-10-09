<?php

namespace ride\cli\command\generator;

use ride\library\config\parser\JsonParser;
use ride\library\system\file\browser\FileBrowser;
use ride\library\system\file\File;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\DocBlock\Tag\ParamTag;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\ParameterGenerator;

class GenerateControllerCommand extends AbstractClassGeneratorCommand {

    /**
     * @var \ride\library\system\file\browser\FileBrowser
     */
    private $fileBrowser;

    /**
     * @var \ride\library\config\parser\JsonParser
     */
    private $jsonParser;

    public function __construct(FileBrowser $fileBrowser, JsonParser $jsonParser) {
        parent::__construct('generate controller');
        $this->fileBrowser = $fileBrowser;
        $this->jsonParser  = $jsonParser;
    }

    /**
     * {@inheritdoc}
     */
    public function execute() {
        $definition = array(
            'namespace' => $namespace = $this->defineNamespace(),
            'class'     => array(
                'names'   => $this->defineControllerName($namespace),
                'methods' => $this->defineActions(),
            ),
            'module'    => $moduleDir = $this->defineModule($this->fileBrowser),
        );

        $controllerFile = $this->createControllerClass($moduleDir, $definition);

        $this->output->writeLine(
            sprintf('Controller "%s" generated at "%s".', $definition['class']['names'][1], $controllerFile->getPath())
        );
    }

    private function defineControllerName($namespace, $question = 'Enter controller name: ') {
        return $this->askAndValidate($this->output, $question, function ($shortName) use ($namespace) {
            if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $shortName)) {
                throw new \InvalidArgumentException('Controller name is invalid.');
            }

            if (false !== strpos($shortName, 'Controller')) {
                throw new \InvalidArgumentException('Controller name should not include "Controller".');
            }

            $shortName = ucfirst($shortName);
            $longName  = "{$shortName}Controller";
            $nsName    = "$namespace\\$longName";

            if (class_exists($nsName)) {
                throw new \InvalidArgumentException(sprintf('Class "%s" already exists.', $nsName));
            }

            return array($shortName, $longName, $nsName);
        });
        /*$rp = '+';
        $ns = '\\';

        list($namespace, $className) = explode($rp, substr_replace($ns, $rp, strrpos($fqcn, $ns)));

        return array($fqcn, $namespace, $className);*/
    }

    private function defineActions($question = 'Enter action name: ') {
        $methods = array();

        $methodNameValidator = function ($shortName) use (&$methods) {
            if (empty($shortName)) {
                return null;
            }

            if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $shortName)) {
                throw new \InvalidArgumentException('Action name is invalid.');
            }

            if (false !== strpos($shortName, 'Action')) {
                throw new \InvalidArgumentException('Action name should not include "Action".');
            }

            $longName = "{$shortName}Action";

            foreach ($methods as $method) {
                if ($method['names'][0] == $shortName) {
                    throw new \InvalidArgumentException('Action name was already defined.');
                }
            }

            return array($shortName, $longName);
        };

        while (null !== $methodNames = $this->askAndValidate($this->output, $question, $methodNameValidator)) {
            $method = array('names' => $methodNames, 'arguments' => array());

            $method['route'] = $this->askAndValidate($this->output, 'Enter action route: ', function ($route) {
                return $route;
            });

            if (preg_match_all('/%(.*?)%/', $method['route'], $matches)) {
                $args = array();

                foreach ($matches[1] as $arg) {
                    $q1         = sprintf('Enter type for argument "%s" [%s]: ', $arg, 'string');
                    $hints      = array('string', 'integer', 'array', 'boolean', 'float', 'double');
                    $args[$arg] = $hints[$this->select($this->output, $q1, $hints, 0)];
                }

                $method['arguments'] = $args;
            }

            $methods[] = $method;
        }

        return $methods;
    }

    private function updateRoutes(File $moduleDir, $fqcn, array $actions) {
        $routesFile = $moduleDir->getChild('config')->getChild('routes.json');
        $routes     = $this->jsonParser->parseToPhp($routesFile->read());

        foreach ($actions as $action) {
            $routes['routes'][] = array(
                'path'       => $action['route'],
                'controller' => $fqcn,
                'action'     => $action['names'][1],
                'id'         => str_replace('\\', '.', $fqcn) . '.' . $action['names'][0]
            );
        }

        $routesFile->write($this->jsonParser->parseFromPhp($routes));
    }

    /**
     * @param File  $moduleDir
     * @param array $definition
     * @return mixed
     */
    protected function createControllerClass(File $moduleDir, $definition) {
        $classGenerator = new ClassGenerator($definition['class']['names'][1], $definition['namespace']);
        $classGenerator->addUse('ride\\web\\base\\controller\\AbstractController');
        $classGenerator->setExtendedClass('AbstractController');

        foreach ($definition['class']['methods'] as $method) {
            $classGenerator->addMethodFromGenerator($methodGenerator = new MethodGenerator($method['names'][1]));
            $methodGenerator->setDocBlock($docBlockGenerator = new DocBlockGenerator($method['route']));

            $methodBody = array();

            foreach ($method['arguments'] as $arg => $type) {
                $methodGenerator->setParameter(new ParameterGenerator($arg));
                $docBlockGenerator->setTag(new ParamTag($arg, $type));
            }

            $methodBody[] = sprintf(
                '$this->setTemplateView("%s/%s", array(%s));',
                strtolower($definition['class']['names'][0]),
                $method['names'][0],
                implode(', ', array_map(function($name) { return sprintf('"%1$s" => $%1$s', $name); }, array_keys($method['arguments'])))
            );

            $methodGenerator->setBody(implode(PHP_EOL, $methodBody));
        }

        $controllerFile = $moduleDir->getChild('src')
            ->getChild(str_replace('\\', DIRECTORY_SEPARATOR, $definition['class']['names'][2]) . '.php');

        $controllerFile->write("<?php\n\n" . $classGenerator->generate());

        $this->updateDependencies($this->jsonParser, $moduleDir, $definition['class']['names'][2]);
        $this->updateRoutes($moduleDir, $definition['class']['names'][2], $definition['class']['methods']);

        return $controllerFile;
    }
}