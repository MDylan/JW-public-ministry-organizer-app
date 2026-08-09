<?php
/*
* @author: Pietro Cinaglia
* 	.website: http://linkedin.com/in/pietrocinaglia
*
* Fork maintained by David Molnar (https://github.com/MDylan/laraupdater).
*/
namespace MDylan\LaraUpdater;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use ZipArchive;

class LaraUpdaterController extends Controller
{

    private $tmp_backup_dir = null;
    private $cache = true;

    private function checkPermission(){

        if( config('laraupdater.allow_users_id') !== null ){

            // 1
            if( config('laraupdater.allow_users_id') === false ) return true;

            // 2
            // Auth::user() may be null when the route is not behind the `auth`
            // middleware; deny instead of fataling on a null property read.
            if( Auth::check() && in_array(Auth::user()->id, config('laraupdater.allow_users_id')) === true ) return true;
        }

        return false;
    }
    /*
    * Download and Install Update.
    */
    public function update()
    {
        echo "<h2>".trans("laraupdater.LaraUpdater")."</h2>";
        echo '<h4><a href="'.url('/').'">'.trans("laraupdater.Return_to_App_HOME").'</a></h4>';

        if( ! $this->checkPermission() ){
            echo trans("laraupdater.ACTION_NOT_ALLOWED.");
            exit;
        }

        // Never start an install from a cached manifest: the cache may be up to
        // `version_check_time` minutes stale.
        $this->cache = false;
        $lastVersionInfo = $this->getLastVersion();

        // version_compare(), not a string comparison: "1.1.10" <= "1.1.5" is
        // TRUE as strings, which would hide every release past x.x.9.
        if( version_compare($lastVersionInfo['version'], $this->getCurrentVersion(), "<=") ) {
            echo '<p>&raquo; '.trans("laraupdater.Your_System_IS_ALREADY_UPDATED_to_last version").' ! '.($lastVersionInfo['version']." <= ".$this->getCurrentVersion()).'</p>';
            exit;
        }

        try {
            $this->tmp_backup_dir = base_path().'/backup_'.date('Ymd');

            echo '<p>'.trans("laraupdater.UPDATE_FOUND").': '.$lastVersionInfo['version'].' <i>('.trans("laraupdater.current_version").': '.$this->getCurrentVersion().')</i></p>';
            echo '<p>'.trans("laraupdater.DESCRIPTION").': <i>'.$lastVersionInfo['description'].'</i></p>';
            echo '<p>&raquo; '.trans("laraupdater.Update_downloading_..").' ';

            $update_path = null;
            if( ($update_path = $this->download($lastVersionInfo['archive'])) === false)
                throw new \Exception(trans("laraupdater.Error_during_download."));

            echo trans("laraupdater.OK").' </p>';

            Artisan::call('down');
            echo '<p>&raquo; '.trans("laraupdater.SYSTEM_Mantence_Mode").' => '.trans("laraupdater.ON").'</p>';

            $status = $this->install($lastVersionInfo['version'], $update_path, $lastVersionInfo['archive']);

            if($status){
                if( config('laraupdater.migrate')==true ) {
                    try {
                        // --force is mandatory: without it `migrate` asks for
                        // confirmation in production and the HTTP request hangs.
                        Artisan::call('migrate', ['--force' => true]);
                    }catch(\Throwable $e) {
                        throw new \Exception(trans("laraupdater.Error_during_download."));
                    }
                }
                $this->setCurrentVersion($lastVersionInfo['version']); //update system version
                // optimize:clear runs clear-compiled, which deletes
                // bootstrap/cache/packages.php and services.php. That is what
                // lets a release change the installed package set (a renamed or
                // added provider) without the next request booting into a
                // "class not found" fatal from a stale discovery cache.
                Artisan::call('optimize:clear');
                Artisan::call('up'); //restore system UP status
                echo '<p>&raquo; '.trans("laraupdater.SYSTEM_Mantence_Mode").' => '.trans("laraupdater.OFF").'</p>';
                echo '<p class="success">'.trans("laraupdater.SYSTEM_IS_NOW_UPDATED_TO_VERSION").': '.$lastVersionInfo['version'].'</p>';
            }else
                throw new \Exception(trans("laraupdater.Error_during_download."));

        } catch (\Exception $e) {
            echo '<p>'.trans("laraupdater.ERROR_DURING_UPDATE_(!!check_the_update_archive!!)");

            $this->restore();

            echo '</p>';
        }
    }

    private function install($lastVersion, $update_path, $archive)
    {
        try{
            $execute_commands = false;
            $upgrade_cmds_filename = 'upgrade.php';
            $upgrade_cmds_path = base_path().config('laraupdater.tmp_path').'/'.$upgrade_cmds_filename;

            $zip = new ZipArchive();
            $zip->open($update_path);
            $extract_tmp_path = base_path().config('laraupdater.tmp_path').'/update';
            $zip->extractTo($extract_tmp_path);

            echo '<p>'.trans("laraupdater.CHANGELOG").': </p>';
            echo '<ul>';

            for ($i = 0; $entry = $zip->statIndex($i); $i++) {
                $filename = $entry['name'];
                $full_path = base_path().'/'.$filename;
                $full_path_tmp = $extract_tmp_path.'/'.$filename;

                if ( is_dir($full_path_tmp) && !file_exists($full_path) ){
                    File::makeDirectory($full_path, $mode = 0755, true, true);
                    $dirname = $filename;
                    echo '<li>'.trans("laraupdater.Directory").' => '.$dirname.'[ '.trans("laraupdater.OK").' ]</li>';
                }

                if ( !is_dir($full_path_tmp) ){ //Overwrite a file with its last version

                    if ( strpos($filename, 'upgrade.php') !== false ) {
                        echo '<li>UPGRADE => '.$filename.'</li>';
                        File::move($full_path_tmp, $upgrade_cmds_path);
                        $execute_commands = true;
                    } else {
                        echo '<li>'.trans("laraupdater.File").' => '.$filename.' ........... ';

                        if(File::exists($full_path)) $this->backup($filename); //backup current version

                        File::move($full_path_tmp, $full_path);
                        echo' [ '.trans("laraupdater.OK").' ]'.'</li>';
                    }

                }
            }
            echo '</ul>';
            $zip->close();

            if($execute_commands == true){
                if(file_exists($upgrade_cmds_path)) {
                    include ($upgrade_cmds_path);

                    if(main()) //upgrade-VERSION.php contains the 'main()' method with a BOOL return to check its execution.
                        echo '<p class="success">&raquo; '. trans("laraupdater.Commands_successfully_executed.") .'</p>';
                    else
                        echo '<p class="danger">&raquo;'. trans("laraupdater.Error_during_commands_execution.") .'</p>';

                    unlink($upgrade_cmds_path);
                    File::delete($upgrade_cmds_path); //clean TMP
                } else {
                    echo '<p class="danger">&raquo;'. trans("laraupdater.Error_during_commands_execution.") .'</p>';
                }
            }

            File::delete($update_path); //clean TMP
            File::deleteDirectory($this->tmp_backup_dir); //remove backup temp folder
            File::deleteDirectory($extract_tmp_path); //remove zip temp folder
            Cache::forget('laraupdater_lastversion');

        } catch (\Exception $e) { return false; }

        return true;
    }

    /*
    * Download Update from $update_baseurl to $tmp_path (local folder).
    */
    private function download($update_name)
    {
        try{
            if(!file_exists(base_path().config('laraupdater.tmp_path'))) {
                File::makeDirectory(base_path().config('laraupdater.tmp_path'), 0750);
            }
            $filename_tmp = base_path().config('laraupdater.tmp_path').'/'.$update_name;

            if ( !is_file( $filename_tmp ) ) {
                $newUpdate = $this->fetch(
                    config('laraupdater.update_baseurl').'/'.$update_name,
                    (int) config('laraupdater.download_timeout', 60)
                );

                $dlHandler = fopen($filename_tmp, 'w');

                if ( !fwrite($dlHandler, $newUpdate) ){
                    echo '<p>'.trans("laraupdater.Could_not_save_new_update").'</p>';
                    exit();
                }
            }

        }catch (\Exception $e) { return false; }

        return $filename_tmp;
    }

    /*
    * Read a resource from the update channel.
    *
    * A bare file_get_contents() has no timeout, so a silent update server
    * blocks the caller until default_socket_timeout (60s by default) elapses -
    * and check() runs on every admin page render. The stream context bounds it.
    * The http wrapper options apply to https too, and are simply ignored when
    * update_baseurl is a plain local path (which is how this is tested).
    */
    private function fetch($url, $timeout)
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
            ],
        ]);

        return file_get_contents($url, false, $context);
    }

    /*
    * Return current version (as plain text).
    */
    public function getCurrentVersion(){
        // todo: env file version
        $version = File::get(base_path().'/version.txt');

        // trim() is load-bearing, not cosmetic: this value is the right-hand
        // side of every version_compare(), and a trailing newline makes
        // version_compare("1.1.5", "1.1.5\n", ">") return TRUE - i.e. the app
        // would advertise an update forever.
        return trim($version);
    }

    /*
    * Check if a new Update exist.
    *
    * Returns the remote version as a STRING, or '' when up to date. Callers
    * that also need the changelog call getDescription(), which is served from
    * the same cache entry.
    */
    public function check()
    {
        $lastVersionInfo = $this->getLastVersion();
        if(is_array($lastVersionInfo)) {
            if( version_compare($lastVersionInfo['version'], $this->getCurrentVersion(), ">") )
                return $lastVersionInfo['version'];
        }
        return '';
    }

    /*
    * Get the update description.
    */
    public function getDescription()
    {
        $lastVersionInfo = $this->getLastVersion();
        if( is_array($lastVersionInfo) && version_compare($lastVersionInfo['version'], $this->getCurrentVersion(), ">") )
            return $lastVersionInfo['description'];

        return '';
    }

    private function setCurrentVersion($last){
        File::put(base_path().'/version.txt', $last); //UPDATE $current_version to last version
    }

    private function getLastVersion($file = "laraupdater.json") {
        $timeout = (int) config('laraupdater.request_timeout', 10);

        if(!$this->cache) {
            $content = $this->fetch(config('laraupdater.update_baseurl').'/'.$file, $timeout);
        } else {
            $content = Cache::remember('laraupdater_lastversion', (config('laraupdater.version_check_time') * 60), function () use ($file, $timeout) {
                try {
                    return $this->fetch(config('laraupdater.update_baseurl').'/'.$file, $timeout);
                } catch(\Exception $e) {
                    // An unreachable channel raises E_WARNING, which Laravel's
                    // HandleExceptions turns into an ErrorException. Without
                    // this catch every admin page render would 500 whenever the
                    // update server is down.
                    return json_encode(array('version' => false));
                }
            });
        }
        $content = json_decode($content, true);
        if(isset($content['previous_version'])) {
            if( version_compare($content['previous_version'], $this->getCurrentVersion(), ">") ) {
                //There are some previous updates, first install those
                Cache::forget('laraupdater_lastversion');
                $this->cache = false;
                $content = $this->getLastVersion("laraupdater-".$content['previous_version'].".json");
            }
        }
        return $content; //['version' => $v, 'archive' => 'RELEASE-$v.zip', 'description' => 'plain text...'];
    }

    private function backup($filename){
        $backup_dir = $this->tmp_backup_dir;

        if ( !is_dir($backup_dir) ) File::makeDirectory($backup_dir, $mode = 0755, true, true);
        if ( !is_dir($backup_dir.'/'.dirname($filename)) ) File::makeDirectory($backup_dir.'/'.dirname($filename), $mode = 0755, true, true);

        File::copy(base_path().'/'.$filename, $backup_dir.'/'.$filename); //to backup folder
    }

    private function restore(){
        if( !isset($this->tmp_backup_dir) )
            $this->tmp_backup_dir = base_path().'/backup_'.date('Ymd');

        try{
            $backup_dir = $this->tmp_backup_dir;
            $backup_files = File::allFiles($backup_dir);

            foreach ($backup_files as $file){
                $filename = (string)$file;
                $filename = substr($filename, (strlen($filename)-strlen($backup_dir)-1)*(-1));
                echo $backup_dir.'/'.$filename." => ".base_path().'/'.$filename;
                File::copy($backup_dir.'/'.$filename, base_path().'/'.$filename); //to respective folder
            }

        }catch(\Exception $e) {
            echo "Exception => ".$e->getMessage();
            echo "<BR>[ ".trans("laraupdater.FAILED")." ]";
            echo "<BR> ".trans("laraupdater.Backup_folder_is_located_in:")." <i>".$backup_dir."</i>.";
            echo "<BR> ".trans("laraupdater.Remember_to_restore_System_UP-Status_through_shell_command:")." <i>php artisan up</i>.";
            return false;
        }

        echo "[ ".trans("laraupdater.RESTORED")." ]";
        return true;
    }
}
