<?php

namespace app\index\controller;

use think\console\Command;

class DoInit extends Command {

    protected function configure() {
        $this->setName('DoInit')->setDescription('Command DoInit');
    }

    protected function execute(\think\console\Input $input, \think\console\Output $output) {
        $path = './initialization';
        $files = $this->scanFile($path);
        foreach ($files as $file) {
            $newFile = str_replace($path, '.', $file);
            copy($file, $newFile);
        }
        echo 'ok!';
    }

    public function scanFile($path) {
        global $result;
        $files = scandir($path);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                if (is_dir($path . '/' . $file)) {
                    $this->scanFile($path . '/' . $file);
                } else {
                    $result[] = $path . '/' . $file;
                }
            }
        }
        return $result;
    }

}
