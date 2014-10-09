<?php

namespace ride\cli\command\generator;

use ride\library\cli\command\extension\AbstractCommand;
use ride\library\config\parser\JsonParser;
use ride\library\system\file\browser\FileBrowser;
use ride\library\system\file\File;

abstract class AbstractClassGeneratorCommand extends AbstractCommand {

    protected function defineNamespace($question = 'Enter namespace: ') {
        return $this->ask($this->output, $question);
    }

    protected function defineModule(FileBrowser $fileBrowser) {
        /** @var File[] $moduleDirs */
        $moduleDirs = $fileBrowser->getApplicationDirectory()
            ->getParent()
            ->getChild('modules')
            ->read();

        $moduleDirs = array_values($moduleDirs);
        $choices    = array();

        foreach ($moduleDirs as $moduleDir) {
            $choices[] = $moduleDir->getName();
        }

        $selectedIndex = $this->select($this->output, 'Enter your module selection: ', $choices);

        return $moduleDirs[$selectedIndex];
    }

    protected function updateDependencies(JsonParser $jsonParser, File $moduleDir, $fqcn,
        $id = null, array $calls = null, array $interfaces = null, array $tags = null, $extends = false) {
        $dependenciesFile = $moduleDir->getChild('config')->getChild('dependencies.json');
        $dependencies     = $jsonParser->parseToPhp($dependenciesFile->read());

        if (null === $id) {
            $id = str_replace('\\', '.', $fqcn);
        }

        $dependency = array('class' => $fqcn, 'id' => $id);

        if (!empty($interfaces)) {
            $dependency['interfaces'] = $interfaces;
        }

        if (!empty($tags)) {
            $dependency['tags'] = $tags;
        }

        if (!empty($calls)) {
            $dependency['calls'] = $calls;
        }

        if ($extends) {
            $dependency['extends'] = $id;
        }

        $dependencies['dependencies'][] = $dependency;

        $dependenciesFile->write($jsonParser->parseFromPhp($dependencies));
    }

}