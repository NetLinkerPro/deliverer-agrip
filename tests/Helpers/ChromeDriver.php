<?php


namespace NetLinker\DelivererAgrip\Tests\Helpers;


use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Dusk\Chrome\ChromeProcess;
use Symfony\Component\Process\Process;

trait ChromeDriver
{
    /**
     * The path to the custom Chromedriver binary.
     *
     * @var string|null
     */
    protected static $chromeDriver;

    /**
     * The Chromedriver process instance.
     *
     * @var \Symfony\Component\Process\Process
     */
    protected static $chromeProcess;

    /**
     * Start the Chromedriver process.
     *
     * @param array $arguments
     * @return void
     *
     * @throws \RuntimeException
     */
    public static function startChromeDriver(array $arguments = [])
    {
        static:: killProcessChromeDriver();
        sleep(1);
        if (Str::contains(php_uname(), 'Mac')) {
            Log::debug(shell_exec('/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome --version'));
        } else {
            Log::debug(shell_exec('google-chrome --version'));
        }

        $chromeDriver = realpath(__DIR__ . '/chromedriver');

        if (!$chromeDriver) {
            throw new \Exception('Nie znaleziono sterownika chrome');
        }

        $process = Process::fromShellCommandline($chromeDriver);
        $process->start();

        while (true) {
            $o = $process->getOutput();
            if (Str::contains($o, 'ChromeDriver was started successfully')) {
                break;
            }
        }
        Log::debug($o);
    }

    public static function killProcessChromeDriver()
    {
        $processName = "chromedriver";

        $command = "ps aux | grep $processName";
        $output = shell_exec($command);

        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            if (strpos($line, $processName) !== false) {
                $parts = preg_split('/\s+/', $line);
                $pid = $parts[1];

                $command = "kill $pid";
                exec($command, $output, $status);

                if ($status != 0) {
                    throw new \Exception("Nie można zamknąć procesu o PID $pid.");
                } else {
                    break;
                }
            }
        }
    }

    /**
     * Stop the Chromedriver process.
     *
     * @return void
     */
    public static function stopChromeDriver()
    {
        if (static::$chromeProcess) {
            static::$chromeProcess->stop();
        }
    }

    /**
     * Build the process to run the Chromedriver.
     *
     * @param array $arguments
     * @return \Symfony\Component\Process\Process
     *
     * @throws \RuntimeException
     */
    protected static function buildChromeProcess(array $arguments = [])
    {
        return (new ChromeProcess(static::$chromeDriver))->toProcess($arguments);
    }

    /**
     * Set the path to the custom Chromedriver.
     *
     * @param string $path
     * @return void
     */
    public static function useChromedriver($path)
    {
        static::$chromeDriver = $path;
    }

}