<?php


// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\ui;

/**
 * Console is a black square component resembling terminal window. It can be programmed
 * to run a job and output results to the user.
 */
class Console extends View implements \Psr\Log\LoggerInterface
{
    public $ui = 'inverted black segment';
    public $element = 'pre';

    /**
     * Will be set to $true while executing callback. Some methods
     * will use this to automatically schedule their own callback
     * and allowing you a cleaner syntax, such as.
     *
     * $console->setModel($user, 'generateReport');
     *
     * @var bool
     */
    protected $sseInProgress = false;

    /**
     * Stores object jsSSE which is used for communication.
     *
     * @var jsSSE
     */
    public $sse;

    /**
     * Bypass is used internally to capture and wrap direct output, but prevent jsSSE from
     * triggering output recurlively.
     *
     * @var bool
     */
    public $_output_bypass = false;

    /**
     * Set a callback method which will be executed with the output sent back to the terminal.
     *
     * Argument passed to your callback will be $this Console. You may perform calls
     * to methods such as
     *
     *   $console->output()
     *   $console->outputHTML()
     *
     * If you are using setModel, and if your model implements atk4\core\DebugTrait,
     * then you you will see debug information generated by $this->debug() or $this->log().
     *
     * This intercepts default application logging for the duration of the process.
     *
     * If you are using runCommand, then server command will be executed with it's output
     * (STDOUT and STDERR) redirected to the console.
     *
     * While inside a callback you may execute runCommand or setModel multiple times.
     */
    public function set($callback = null, $junk = null)
    {
        if (!$callback) {
            throw new Exception('Please specify the $callback argument');
        }
        $this->sse = $this->add('jsSSE');
        $this->sse->set(function () use ($callback) {
            $this->sseInProgress = true;

            try {
                ob_start(function ($content) {
                    if ($this->_output_bypass) {
                        return $content;
                    }

                    $output = '';
                    $this->sse->echoFunction = function ($str) use (&$output) {
                        $output .= $str;
                    };
                    $this->output($content);
                    $this->sse->echoFunction = false;

                    return $output;
                }, 2);

                call_user_func($callback, $this);
            } catch (\atk4\core\Exception $e) {
                $lines = explode("\n", $e->getHTMLText());

                foreach ($lines as $line) {
                    $this->outputHTML($line);
                }
            } catch (\Error $e) {
                $this->output('Error: '.$e->getMessage());
            } catch (\Exception $e) {
                $this->output('Exception: '.$e->getMessage());
            }
            $this->sseInProgress = false;
        });
        $this->js(true, $this->sse);

        return $this;
    }

    /**
     * Output a single line to the console.
     *
     * @param $text string
     *
     * @return $this
     */
    public function output($text)
    {
        $this->outputHTML(htmlspecialchars($text));

        return $this;
    }

    /**
     * Output un-escaped HTML line. Use this to send HTML.
     *
     * @param $text string
     *
     * @return $this
     */
    public function outputHTML($text)
    {
        $this->_output_bypass = true;
        $this->sse->send($this->js()->append($text.'<br/>'));
        $this->_output_bypass = false;

        return $this;
    }

    /**
     * Executes command passing along escaped arguments.
     *
     * Will also stream stdout / stderr as the comand executes.
     * once command terminates method will return the exit code.
     *
     * This method can be executed from inside callback or
     * without it.
     */

    /*
    public function runCommand($exec, $args = [])
    {
        if (!$this->sseInProgress) {
            $this->set(function () {
                $this->runCommand($exec, $args);
            });

            return;
        }


        // not implemented here
        //
        //
    }
     */

    /**
     * Execute method of a certain model. That's a short-hand method
     * for running:.
     *
     * $app->add('Console')->setModel(new User($db), 'generateReports');
     *
     * You can enable output from inside your method if you:
     *
     *  - implement \atk4\core\DebugTrait in your model
     *  - use $this->debug() or $this->info()
     *  - if you wish to get log from other objects, be sure to switch debug on with $obj->debug = true;
     *
     * @param $model \atk4\data\Model
     * @param $method string
     * @param $args array
     */
    public function setModel(\atk4\data\Model $model, string $method = null, $args = [])
    {
        if (!$method) {
            throw new Exception('You must specify $method argument');
        }
        if (!$this->sseInProgress) {
            $this->set(function () use ($model, $method, $args) {
                $this->setModel($model, $method, $args);
            });

            return;
        }

        // temporarily override app logging
        if (isset($model->app)) {
            $old_logger = $model->app->logger;
            $model->app->logger = $this;
        }

        $this->output('--[ Executing '.get_class($model).'->'.$method.' ]--------------');
        $model->debug = true;
        $result = call_user_func_array([$model, $method], $args);
        $this->output('--[ Result: '.json_encode($result).' ]------------');

        if (isset($model->app)) {
            $model->app->logger = $old_logger;
        }
    }

    public function emergency($str, array $args = [])
    {
        return $this->outputHTML("<font color='pink'>".htmlspecialchars($str).'</font>');
    }

    public function alert($str, array $args = [])
    {
        return $this->outputHTML("<font color='pink'>".htmlspecialchars($str).'</font>');
    }

    public function critical($str, array $args = [])
    {
        return $this->outputHTML("<font color='pink'>".htmlspecialchars($str).'</font>');
    }

    public function error($str, array $args = [])
    {
        return $this->outputHTML("<font color='pink'>".htmlspecialchars($str).'</font>');
    }

    public function warning($str, array $args = [])
    {
        return $this->outputHTML("<font color='pink'>".htmlspecialchars($str).'</font>');
    }

    public function notice($str, array $args = [])
    {
        return $this->outputHTML("<font color='yellow'>".htmlspecialchars($str).'</font>');
    }

    public function info($str, array $args = [])
    {
        return $this->outputHTML("<font color='gray'>".htmlspecialchars($str).'</font>');
    }

    public function debug($str, array $args = [])
    {
        return $this->outputHTML("<font color='cyan'>".htmlspecialchars($str).'</font>');
    }

    public function log($level, $str, array $args = [])
    {
        return $this->$level($str, $args);
    }
}
