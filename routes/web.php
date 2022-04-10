<?php

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

Route::get('/test', function () {

    

    return view('test');
})->name('test');
// https://laravel.com/docs/master/routing#named-routes