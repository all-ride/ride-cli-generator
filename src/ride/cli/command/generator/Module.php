<?php

namespace ride\library\ride;

use ride\library\config\parser\JsonParser;
use ride\library\dependency\Dependency;
use ride\library\dependency\DependencyCall;
use ride\library\dependency\DependencyCallArgument;
use ride\library\router\Route;
use ride\library\system\file\File;
use Zend\Code\Generator\ClassGenerator;

class Module {

    /**
     * @var File
     */
    private $moduleDir;

    /**
     * @var File
     */
    private $configDir;

    /**
     * @var File
     */
    private $sourceDir;

    public function __construct(File $moduleDir) {
        $this->moduleDir  = $moduleDir;
        $this->configDir  = $moduleDir->getChild('config');
        $this->sourceDir  = $moduleDir->getChild('src');
        $this->jsonParser = new JsonParser();
    }

    public function addClass(ClassGenerator $generator, $dependency = false) {
        $file = $this->sourceDir;

        if ($generator->getNamespaceName()) {
            $file = $file->getChild(str_replace('\\', DIRECTORY_SEPARATOR, $generator->getNamespaceName()));
        }

        $prefix = "<?php" . $generator::LINE_FEED . $generator::LINE_FEED;
        $file->getChild($generator->getName() . '.php')->write($prefix . $generator->generate());

        if ($dependency) {
            $this->addDependency(new Dependency($generator->getNamespaceName() .'\\'. $generator->getName()));
        }
    }

    private function mapDependencyToArray(Dependency $dependency, $extends = false) {
        if (null === $id = $dependency->getId()) {
            $id = str_replace('\\', '.', $dependency->getClassName());
        }

        $array = array('class' => $dependency->getClassName(), 'id' => $id);

        if ($dependency->getInterfaces()) {
            $array['interfaces'] = $dependency->getInterfaces();
        }

        if ($dependency->getTags()) {
            $array['tags'] = $dependency->getTags();
        }

        if ($dependency->getCalls()) {
            foreach ($dependency->getCalls() as $call) {
                /** @var DependencyCall $call */
                $c = array('method' => $call->getMethodName());
                foreach ($call->getArguments() as $arg) {
                    /** @var DependencyCallArgument $arg */
                    $c['arguments'][] = array(
                        'name'       => $arg->getName(),
                        'type'       => $arg->getType(),
                        'properties' => $arg->getProperties(),
                    );
                }
            }
        }

        if ($extends) {
            $array['extends'] = $id;
        }

        return $array;
    }

    public function addDependency(Dependency $dependency, $extends = false) {
        $file = $this->configDir->getChild('dependencies.json');

        $this->executeReadWrite($file, 'dependencies',
            array($this, 'mapDependencyToArray'), array($dependency, $extends)
        );
    }

    private function mapRouteToArray(Route $route) {
        $array = array('path' => $route->getPath());

        if (is_array($callback = $route->getCallback())) {
            $array['controller'] = $callback[0];
            $array['action']     = $callback[1];
        } else {
            $array['controller'] = $callback;
        }

        if ($route->getId()) {
            $array['id'] = $route->getId();
        }

        return $array;
    }

    public function addRoute(Route $route) {
        $file = $this->configDir->getChild('routes.json');

        $this->executeReadWrite($file, 'routes',
            array($this, 'mapRouteToArray'), array($route)
        );
    }

    /**
     * @param \ride\library\system\file\File $file
     * @param string                         $key
     * @param callable                       $callback
     * @param array                          $arguments
     * @throws \ride\library\config\exception\ConfigException
     */
    private function executeReadWrite(File $file, $key, $callback, array $arguments = array()) {
        $data = $this->jsonParser->parseToPhp($file->read());

        $data[$key][] = call_user_func_array($callback, $arguments);

        $file->write($this->jsonParser->parseFromPhp($data));
    }
}