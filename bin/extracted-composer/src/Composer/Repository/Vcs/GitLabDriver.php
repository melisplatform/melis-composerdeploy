<?php











namespace Composer\Repository\Vcs;

use Composer\Config;
use Composer\Cache;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Downloader\TransportException;
use Composer\Util\RemoteFilesystem;
use Composer\Util\GitLab;







class GitLabDriver extends VcsDriver
{
private $scheme;
private $namespace;
private $repository;




private $project;




private $commits = array();




private $tags;




private $branches;






protected $gitDriver;






private $isPrivate = true;

const URL_REGEX = '#^(?:(?P<scheme>https?)://(?P<domain>.+?)/|git@(?P<domain2>[^:]+):)(?P<parts>.+)/(?P<repo>[^/]+?)(?:\.git|/)?$#';








public function initialize()
{
if (!preg_match(self::URL_REGEX, $this->url, $match)) {
throw new \InvalidArgumentException('The URL provided is invalid. It must be the HTTP URL of a GitLab project.');
}

$guessedDomain = !empty($match['domain']) ? $match['domain'] : $match['domain2'];
$configuredDomains = $this->config->get('gitlab-domains');
$urlParts = explode('/', $match['parts']);

$this->scheme = !empty($match['scheme'])
? $match['scheme']
: (isset($this->repoConfig['secure-http']) && $this->repoConfig['secure-http'] === false ? 'http' : 'https')
;
$this->originUrl = $this->determineOrigin($configuredDomains, $guessedDomain, $urlParts);
$this->namespace = implode('/', $urlParts);
$this->repository = preg_replace('#(\.git)$#', '', $match['repo']);

$this->cache = new Cache($this->io, $this->config->get('cache-repo-dir').'/'.$this->originUrl.'/'.$this->namespace.'/'.$this->repository);

$this->fetchProject();
}







public function setRemoteFilesystem(RemoteFilesystem $remoteFilesystem)
{
$this->remoteFilesystem = $remoteFilesystem;
}




public function getFileContent($file, $identifier)
{
if ($this->gitDriver) {
return $this->gitDriver->getFileContent($file, $identifier);
}


 if (!preg_match('{[a-f0-9]{40}}i', $identifier)) {
$branches = $this->getBranches();
if (isset($branches[$identifier])) {
$identifier = $branches[$identifier];
}
}

$resource = $this->getApiUrl().'/repository/files/'.$this->urlEncodeAll($file).'/raw?ref='.$identifier;

try {
$content = $this->getContents($resource);
} catch (TransportException $e) {
if ($e->getCode() !== 404) {
throw $e;
}

return null;
}

return $content;
}




public function getChangeDate($identifier)
{
if ($this->gitDriver) {
return $this->gitDriver->getChangeDate($identifier);
}

if (isset($this->commits[$identifier])) {
return new \DateTime($this->commits[$identifier]['committed_date']);
}

return new \DateTime();
}




public function getRepositoryUrl()
{
return $this->isPrivate ? $this->project['ssh_url_to_repo'] : $this->project['http_url_to_repo'];
}




public function getUrl()
{
if ($this->gitDriver) {
return $this->gitDriver->getUrl();
}

return $this->project['web_url'];
}




public function getDist($identifier)
{
$url = $this->getApiUrl().'/repository/archive.zip?sha='.$identifier;

return array('type' => 'zip', 'url' => $url, 'reference' => $identifier, 'shasum' => '');
}




public function getSource($identifier)
{
if ($this->gitDriver) {
return $this->gitDriver->getSource($identifier);
}

return array('type' => 'git', 'url' => $this->getRepositoryUrl(), 'reference' => $identifier);
}




public function getRootIdentifier()
{
if ($this->gitDriver) {
return $this->gitDriver->getRootIdentifier();
}

return $this->project['default_branch'];
}




public function getBranches()
{
if ($this->gitDriver) {
return $this->gitDriver->getBranches();
}

if (!$this->branches) {
$this->branches = $this->getReferences('branches');
}

return $this->branches;
}




public function getTags()
{
if ($this->gitDriver) {
return $this->gitDriver->getTags();
}

if (!$this->tags) {
$this->tags = $this->getReferences('tags');
}

return $this->tags;
}




public function getApiUrl()
{
return $this->scheme.'://'.$this->originUrl.'/api/v4/projects/'.$this->urlEncodeAll($this->namespace).'%2F'.$this->urlEncodeAll($this->repository);
}







private function urlEncodeAll($string)
{
$encoded = '';
for ($i = 0; isset($string[$i]); $i++) {
$character = $string[$i];
if (!ctype_alnum($character) && !in_array($character, array('-', '_'), true)) {
$character = '%' . sprintf('%02X', ord($character));
}
$encoded .= $character;
}

return $encoded;
}






protected function getReferences($type)
{
$perPage = 100;
$resource = $this->getApiUrl().'/repository/'.$type.'?per_page='.$perPage;

$references = array();
do {
$data = JsonFile::parseJson($this->getContents($resource), $resource);

foreach ($data as $datum) {
$references[$datum['name']] = $datum['commit']['id'];


 
 $this->commits[$datum['commit']['id']] = $datum['commit'];
}

if (count($data) >= $perPage) {
$resource = $this->getNextPage();
} else {
$resource = false;
}
} while ($resource);

return $references;
}

protected function fetchProject()
{

 $resource = $this->getApiUrl();
$this->project = JsonFile::parseJson($this->getContents($resource, true), $resource);
if (isset($this->project['visibility'])) {
$this->isPrivate = $this->project['visibility'] !== 'public';
} else {

 $this->isPrivate = false;
}
}

protected function attemptCloneFallback()
{
try {
if ($this->isPrivate === false) {
$url = $this->generatePublicUrl();
} else {
$url = $this->generateSshUrl();
}


 
 
 $this->setupGitDriver($url);

return;
} catch (\RuntimeException $e) {
$this->gitDriver = null;

$this->io->writeError('<error>Failed to clone the '.$url.' repository, try running in interactive mode so that you can enter your credentials</error>');
throw $e;
}
}






protected function generateSshUrl()
{
return 'git@' . $this->originUrl . ':'.$this->namespace.'/'.$this->repository.'.git';
}

protected function generatePublicUrl()
{
return 'https://' . $this->originUrl . '/'.$this->namespace.'/'.$this->repository.'.git';
}

protected function setupGitDriver($url)
{
$this->gitDriver = new GitDriver(
array('url' => $url),
$this->io,
$this->config,
$this->process,
$this->remoteFilesystem
);
$this->gitDriver->initialize();
}




protected function getContents($url, $fetchingRepoData = false)
{
try {
$res = parent::getContents($url);

if ($fetchingRepoData) {
$json = JsonFile::parseJson($res, $url);


 if (!isset($json['default_branch'])) {
if (!empty($json['id'])) {
$this->isPrivate = false;
}

throw new TransportException('GitLab API seems to not be authenticated as it did not return a default_branch', 401);
}
}

return $res;
} catch (TransportException $e) {
$gitLabUtil = new GitLab($this->io, $this->config, $this->process, $this->remoteFilesystem);

switch ($e->getCode()) {
case 401:
case 404:

 if (!$fetchingRepoData) {
throw $e;
}

if ($gitLabUtil->authorizeOAuth($this->originUrl)) {
return parent::getContents($url);
}

if (!$this->io->isInteractive()) {
return $this->attemptCloneFallback();
}
$this->io->writeError('<warning>Failed to download ' . $this->namespace . '/' . $this->repository . ':' . $e->getMessage() . '</warning>');
$gitLabUtil->authorizeOAuthInteractively($this->scheme, $this->originUrl, 'Your credentials are required to fetch private repository metadata (<info>'.$this->url.'</info>)');

return parent::getContents($url);

case 403:
if (!$this->io->hasAuthentication($this->originUrl) && $gitLabUtil->authorizeOAuth($this->originUrl)) {
return parent::getContents($url);
}

if (!$this->io->isInteractive() && $fetchingRepoData) {
return $this->attemptCloneFallback();
}

throw $e;

default:
throw $e;
}
}
}







public static function supports(IOInterface $io, Config $config, $url, $deep = false)
{
if (!preg_match(self::URL_REGEX, $url, $match)) {
return false;
}

$scheme = !empty($match['scheme']) ? $match['scheme'] : null;
$guessedDomain = !empty($match['domain']) ? $match['domain'] : $match['domain2'];
$urlParts = explode('/', $match['parts']);

if (false === self::determineOrigin((array) $config->get('gitlab-domains'), $guessedDomain, $urlParts)) {
return false;
}

if ('https' === $scheme && !extension_loaded('openssl')) {
$io->writeError('Skipping GitLab driver for '.$url.' because the OpenSSL PHP extension is missing.', true, IOInterface::VERBOSE);

return false;
}

return true;
}

private function getNextPage()
{
$headers = $this->remoteFilesystem->getLastHeaders();
foreach ($headers as $header) {
if (preg_match('{^link:\s*(.+?)\s*$}i', $header, $match)) {
$links = explode(',', $match[1]);
foreach ($links as $link) {
if (preg_match('{<(.+?)>; *rel="next"}', $link, $match)) {
return $match[1];
}
}
}
}
}







private static function determineOrigin(array $configuredDomains, $guessedDomain, array &$urlParts)
{
if (in_array($guessedDomain, $configuredDomains)) {
return $guessedDomain;
}

while (null !== ($part = array_shift($urlParts))) {
$guessedDomain .= '/' . $part;

if (in_array($guessedDomain, $configuredDomains)) {
return $guessedDomain;
}
}

return false;
}
}
