<?php

namespace modmore\Gitify\Mixins;

/**
 * Class DownloadModx
 * @package modmore\Gitify\Mixins
 */
trait DownloadModx
{
    /**
     * Downloads the latest version of MODX or takes from local cache, and puts it into place in the working directory.
     *
     * @param string $version
     * @param bool $download
     * @param bool $advanced
     * @return bool
     */
    protected function getMODX($version = 'latest', $download = false, $advanced = false)
    {
        $advanced = $advanced ? '-advanced' : false;
        if ($version === 'latest') {
            $this->output->writeln('Looking up latest MODX version...');
            $link = $this->fetchUrl($version);
            $version = basename($link, '.zip');
            $this->output->writeln('<comment>Latest version: ' . $version . '</comment>');
        }

        // Force download the MODX package
        if ($download) {
            $this->output->writeln('<comment>Ignoring local cache, downloading MODX package...</comment>');
            if (!$this->download($version, $advanced)) {
                return false;
            }
        }

        // Copy the files from the local cache (and download version if necessary)
        $this->retrieveFromCache($version, $advanced);

        return true;
    }

    /**
     * Downloads specified package to local storage
     *
     * @param $version
     * @param $advanced
     * @return bool
     */
    protected function download($version, $advanced)
    {
        $link = $this->fetchUrl($version);

        $link = str_replace('.zip', $advanced ? "{$advanced}.zip" : ".zip", $link);
        if (!file_exists(GITIFY_CACHE_DIR)) {
            mkdir(GITIFY_CACHE_DIR);
        }

        $this->removeOutdatedArchive($version, $advanced); // remove old files

        $zip = GITIFY_CACHE_DIR . $version . $advanced . '.zip';
        $this->output->writeln("Downloading {$version}{$advanced} from {$link}...");
        exec("curl -Lo $zip $link -#");

        if (!file_exists($zip)) {
            $this->output->writeln('<error>Error: Could not download the MODX zip</error>');

            return false;
        }

        $this->unzip($zip, $version, $advanced);

        return true;
    }

    /**
     * Unzips the package zip to the current directory
     *
     * @param $package
     */
    protected function unzip($package, $version, $advanced = '-traditional')
    {
        $this->output->writeln("Extracting package...");

        $destination = dirname($package);
        exec("unzip $package -d $destination");
        exec("mv {$destination}/{$version} {$destination}/{$version}{$advanced}");
    }

    /**
     * Fetches the real download link for a MODX package version
     *
     * @param $version
     * @return mixed
     */
    protected function fetchUrl($version)
    {
        if ($this->output->isVerbose()) {
            $this->output->writeln('Fetching download URL for MODX ' . $version);
        }
        $version = str_replace('modx-', '', $version);
        $url = empty($version) || $version == 'latest'
            ? 'https://modx.com/download/latest/'
            : 'https://modx.com/download/direct/modx-' . $version . '.zip';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_exec($ch);
        $direct = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if ($this->output->isVerbose()) {
            $this->output->writeln('Download URL found: ' . $direct);
        }
        return $direct;
    }

    /**
     * Copies the MODX files from the local cache into the current directory, or downloads the specified version
     * if it isn't cached yet.
     *
     * @param $version
     */
    protected function retrieveFromCache($version, $advanced)
    {
        $version = 'modx-' . str_replace('modx-', '', $version);


        $path = GITIFY_CACHE_DIR . $version . $advanced;

        if (!file_exists($path) || !is_dir($path)) {
            $this->download($version, $advanced);
        }

        exec("cp -r $path/* ./");
    }

    /**
     * Removes old cache folders
     *
     * @param $version
     */
    protected function removeOutdatedArchive($version, $advanced)
    {
        $folder = GITIFY_CACHE_DIR . $version . $advanced;
        $package = $folder . '.zip';

        exec("rm -rf $folder");
        exec("rm -rf $package");
    }
}
