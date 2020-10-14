<?php
namespace App\Commands;

use App\Traits\CommandInterface;
use App\Traits\CommandTrait;
use Composer\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;
use Throwable;

class Deploy implements CommandInterface
{
    use CommandTrait;
    public static $name = "Deploy";
    public static $subCommands = [];
    /**
     * Send deploy commands to the project root.
     *
     * @param  \Discord\Parts\Channel\Message $message
     * @param  array                          $params
     * @return void
     */
    public function handle($message, $params)
    {
        $env = strtoupper($params[0] ??= 'production');
        $ci_path = $this->app->getEnvCIPath($env);
        if (!in_array($env, explode(",", $_ENV['ALLOWED_ENVS'])) ) {
            return "Ambiente não permitido.";
        }
        if (!$ci_path) {
            return "Não há o diretório para esse ambiente cadastro no .env";
        }
        try{
            $message->channel->sendMessage(
                "", false,
                $this->embed(
                    "Composer Install - CodeIgniter", 
                    $this->composerInstall($ci_path)
                ) 
            )->then(
                function () use ($message, $ci_path) {
                    $message->channel->sendMessage(
                        "", false,
                        $this->embed(
                            "Phinx - CodeIgniter", 
                            $this->runCommand("php {$ci_path}/vendor/robmorgan/phinx/bin/phinx migrate --configuration=".realpath("{$ci_path}/phinx.php"))
                        )
                    );
                }
            );
            $message->channel->sendMessage(
                "", false,
                $this->embed(
                    "Composer Install - Lumen", 
                    $this->composerInstall($ci_path . "/a")
                ) 
            )->then(
                function () use ($message, $ci_path) {
                    $message->channel->sendMessage(
                        "", false,
                        $this->embed(
                            "Artisan Migrate - Lumen", 
                            $this->runCommand("php {$ci_path}/a/artisan migrate")
                        )
                    )->then(
                        function () use ($message, $ci_path) {
                            $message->channel->sendMessage(
                                "", false,
                                $this->embed(
                                    "Scout Mysql - Lumen", 
                                    $this->runCommand("php {$ci_path}/a/artisan scout:mysql-index")
                                )
                            );
                        }
                    );
                }
            );
        }catch(Throwable $e){
            $message->channel->sendMessage($e->getMessage());
        }
    }
    /**
     * Make an array of embed options.
     *
     * @param string $title
     * @param string $description
     * 
     * @return array
     * @see    https://birdie0.github.io/discord-webhooks-guide/structure/embeds.html - options for embed
     */
    public function embed(string $title, string $description)
    {
        return [
            'title' => $title,
            'description' => preg_replace("/\r|\n/", "", $description)
        ];
    }
    public function composerInstall($dir)
    {
        return $this->runComposerCommand(['command' => 'install', '-d' => $dir], $dir);
    }
    /**
     * Run an composer command on specified dir. The $dir must have an installed composer.
     *
     * @param array  $command - array of command to run like ["command" => "install"]
     * @param string $dir
     * 
     * @return string
     */
    public function runComposerCommand(array $command, string $dir)
    {
        putenv("COMPOSER_HOME=".realpath("$dir/vendor/bin/composer"));
        chdir(__DIR__);
        $stream = fopen('php://temp', 'w+');
        $output = new StreamOutput($stream);
        $application = new ConsoleApplication();
        $application->setAutoExit(false);
        $application->run(new ArrayInput($command), $output);
        rewind($stream);
        $response = stream_get_contents($stream);
        return preg_replace("/((<)|(<\/))warning>/i", " \n ", $response);
    }
    /**
     * Open an process and run specified command and return its output.
     *
     * @param string $command
     * 
     * @return string|bool
     */
    public function runCommand(string $command)
    {
        $handle = popen($command, 'r');
        $read = '';
        while(!feof($handle)){
            $read .= fgets($handle, 2096);
        }
        pclose($handle);
        
        return $read;
    }
}
