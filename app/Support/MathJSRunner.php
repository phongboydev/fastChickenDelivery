<?php

namespace App\Support;

use Exception;
use Symfony\Component\Process\InputStream;

class MathJSRunner
{

    /** @var resource */
    protected $process;
    /** @var InputStream */
    protected InputStream $input;

    protected array $logs = [];
    private array $pipes;

    /**
     * @throws Exception
     */
    public function start()
    {
        $cmd = config("mathjs.binary_path");

        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w"),
        );

        $process = proc_open($cmd, $descriptorspec, $pipes);
        if (!is_resource($process)) {
            throw new Exception("Unable to start process.");
        }

        $this->process = $process;
        $this->pipes = $pipes;
    }

    public function formulas(array $formulas)
    {
        $this->command("FORMULA", json_encode($formulas));
    }

    /**
     * Set constant variable
     * @param $variable
     * @param $value
     */
    public function set($variable, $value)
    {
        $this->command("SET", "$variable=$value");
    }

    /**
     * Set function variable
     * @param $variable
     * @param $value
     */
    public function setf($variable, $value)
    {
        $this->command("SETF", "$variable=$value");
    }

    public function clear()
    {
        $this->command("CLEAR");
    }

    public function calc()
    {
        $this->command("CALC");
    }

    protected function command(string $command, string $param = "")
    {
        $param = str_replace("\n", "", $param);
        $input = "$command $param";
        array_push($this->logs, $input);
        fwrite($this->pipes[0], $input . "\n");
    }

    public function getResult() : array
    {
        fclose($this->pipes[0]);
        $result = [];
        $row = [];
        while($output = fgets($this->pipes[1]))
        {
            if ($output == "END\n") {
                array_push($result, $row);
                $row = [];
            } else {
                $parts = explode("=", $output);
                if (count($parts) == 2) {
                    $row[$parts[0]] = trim($parts[1]);
                }
            }
        }
        fclose($this->pipes[1]);
        fclose($this->pipes[2]);
        return $result;
    }

    public function getError() : string
    {
        $result = "";
        while($output = fgets($this->pipes[2]))
        {
            $result .= $output;
        }
        return $result;
    }

    public function getLogs() : array
    {
        return $this->logs;
    }
}
