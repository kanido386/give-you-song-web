<?php

// for Spotify
// require 'vendor/autoload.php';

// FIXME:
// https://stackoverflow.com/questions/9650090/undefined-variable-session
session_start();

use Illuminate\Support\Facades\Route;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/



abstract class Territory
{
    const Taiwan = 'TW';
    const HongKong = 'HK';
    const Singapore = 'SG';
    const Malaysia = 'MY';
    const Japan = 'JP';
}


class AccessToken {
    // TODO:
    public $accessToken = null;
    protected $tokenType = null;
    protected $expiresIn = null;

    public function __construct($accessToken, $tokenType, $expiresIn)
    {
        $this->accessToken = $accessToken;
        $this->tokenType = $tokenType;
        $this->expiresIn = $expiresIn;
    }
}

class SpotifyService {

    protected $SpotifyClientID = null;
    protected $SpotifyClientSecret = null;
    protected $RedirectURI = "http://localhost/";
    
    public $api = null;
    protected $accessToken = null;

    public function __construct()
    {
        $this->SpotifyClientID = env('SPOTIPY_CLIENT_ID');
        $this->SpotifyClientSecret = env('SPOTIPY_CLIENT_SECRET');

        $session = new SpotifyWebAPI\Session(
            $this->SpotifyClientID,
            $this->SpotifyClientSecret,
            $this->RedirectURI
        );

        $api = new SpotifyWebAPI\SpotifyWebAPI();

        if (isset($_GET['code'])) {
            $session->requestAccessToken($_GET['code']);
            $this->accessToken = $session->getAccessToken();
            $api->setAccessToken($this->accessToken);
        
            // var_dump($api->me());
        } else {
            $options = [
                'scope' => [
                    'user-read-email',
                ],
            ];
        
            header('Location: ' . $session->getAuthorizeUrl($options));
            die();
        }
    }
}

// TODO:
$api = null;
// $GLOBALS['api'] = null;

class OpenAPI {
    protected $clientID = null;
    protected $clientSecret = null;
    protected $accessToken = null;

    // TODO:
    protected $API_END_POINT = "https://api.kkbox.com/v1.1";

    public function __construct($clientID, $clientSecret)
    {
        $this->clientID = $clientID;
        $this->clientSecret = $clientSecret;
    }

    public function fetchAccessTokenByClientCredential()
    {
        $base = $this->clientID . ":" . $this->clientSecret;
        $credentials = base64_encode($base);
        $client = new Client();
        $headers = [
            'Authorization' => 'Basic ' . $credentials,
            'Content-type' => 'application/x-www-form-urlencoded',
            'User-Agent' => 'KKBOX Open API PHP SDK',
        ];
        $body = 'grant_type=client_credentials';
        $request = new Request('POST', 'https://account.kkbox.com/oauth2/token', $headers, $body);
        return $client->send($request);
    }

    public function fetchAndUpdateAccessToken()
    {
        // TODO: No error handling
        $response = $this->fetchAccessTokenByClientCredential();
        $jsonObject = json_decode($response->getBody());
        $accessToken = $jsonObject->access_token;
        $tokenType = $jsonObject->token_type;
        $expiresIn = $jsonObject->expires_in;
        $accessTokenObject = new AccessToken($accessToken, $tokenType, $expiresIn);
        $this->accessToken = $accessTokenObject;
    }

    private function fetch($url)
    {
        $client = new Client();
        $headers = [
            // TODO:
            'Authorization' => 'Bearer ' . $this->accessToken->accessToken,
            'User-Agent' => 'KKBOX Open API PHP SDK',
        ];
        $request = new Request('GET', $url, $headers);
        return $client->send($request);
    }

    public function fetchCharts($territory = Territory::Taiwan, $offset = 0, $limit = 30)
    {
        $url = $this->API_END_POINT . "/charts?territory=$territory&offset=$offset&limit=$limit";
        return $this->fetch($url);
    }

    public function fetchTracksInChart($playlistID, $territory = Territory::Taiwan, $offset = 0, $limit = 30)
    {
        $url = $this->API_END_POINT . "/charts/$playlistID/tracks?territory=$territory&offset=$offset&limit=$limit";
        return $this->fetch($url);
    }

    public function getCleanerName($name)
    {
        $cleanerName = $name;

        // turn
        // "想見你想見你想見你 (Miss You 3000) - 電視劇<想見你>片尾曲"
        // to
        // "想見你想見你想見你 (Miss You 3000)"
        $cleanerName = explode(" -", $cleanerName)[0];

        // turn
        // "想見你想見你想見你 (Miss You 3000)"
        // to
        // "想見你想見你想見你"
        $cleanerName = explode(" (", $cleanerName)[0];

        return $cleanerName;
    }
}

Route::get('/', function () {
    $clientID = env('KKBOX_CLIENT_ID');
    $clientSecret = env('KKBOX_CLIENT_SECRET');
    $openAPI = new OpenAPI($clientID, $clientSecret);
    $openAPI->fetchAndUpdateAccessToken();

    $response = $openAPI->fetchCharts();
    $results = json_decode($response->getBody(), true);
    // $out = new \Symfony\Component\Console\Output\ConsoleOutput();
    // $out->writeln(serialize($results));

    $charts = array();
    foreach ($results["data"] as $item) {
        $id = $item["id"];
        $title = $item["title"];
        $charts[$id] = $title;
    }

    // FIXME: bad way
    $num = 5;
    $randomIndex = array_rand($charts, $num);
    $randomCharts = array();
    foreach ($randomIndex as $index => $id) {
        $randomCharts[$id] = $charts[$id];
    }
    // var_dump($charts);
    return view('welcome', ['charts' => $randomCharts]);
});

Route::get('/test/{id}', function ($id) {
    
    $clientID = env('KKBOX_CLIENT_ID');
    $clientSecret = env('KKBOX_CLIENT_SECRET');
    $openAPI = new OpenAPI($clientID, $clientSecret);
    $openAPI->fetchAndUpdateAccessToken();

    $response = $openAPI->fetchTracksInChart($id);
    $results = json_decode($response->getBody(), true);
    // $out = new \Symfony\Component\Console\Output\ConsoleOutput();
    // $out->writeln(serialize($results));

    $tracks = array();
    $index = 0;
    foreach ($results["data"] as $item) {
        $artistName = $openAPI->getCleanerName($item["album"]["artist"]["name"]);
        $songName = $openAPI->getCleanerName($item["name"]);
        $tracks[$index] = array($artistName, $songName);
        $index++;
    }

    // FIXME: bad way
    $num = 5;
    $randomIndex = array_rand($tracks, $num);
    $randomTracks = array();
    foreach ($randomIndex as $i => $index) {
        $randomTracks[$index] = $tracks[$index];
    }
    var_dump($randomTracks);


    // FIXME:
    // global $api;
    $query = "盧廣仲";
    $type = "track";
    // $api = Session::get('api');
    // https://stackoverflow.com/questions/1442177/storing-objects-in-php-session
    $api = unserialize($_SESSION['api']);
    var_dump($api->search($query, $type));
    // var_dump($GLOBALS['api']->search($query, $type));

    return view('test', ['tracks' => $randomTracks]);
})->name('test');
// https://laravel.com/docs/master/routing#named-routes


Route::get('/login', function () {

    // FIXME:
    // global $api;
    $api = new SpotifyService();
    // $GLOBALS['api'] = new SpotifyService();
    // Session::put('api', $api);

    // https://stackoverflow.com/questions/1442177/storing-objects-in-php-session
    $_SESSION['api'] = serialize($api);

    return redirect('/');
});

// Route::get('/wow/{id}', function ($id) {

//     return view('test', ['tracks' => $randomTracks]);
// })->name('test');