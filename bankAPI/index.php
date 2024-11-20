<?php
//klasa odpowiadająca za routing czyli przetwarzanie URL zapytania skierowanego do serwera API
//wszystko po localhost/bankAPI trafi dzieki temu do tego skryptu
require_once('Route.php');
//model odpowiadający za tabelę account w bazie danych - umożliwia operacje na rachunkach
require_once('model/Account.php');
//model użytkownika
require_once('model/User.php');
//model tokena
require_once('model/Token.php');
require_once('model/Transfer.php');

//połączenie do bazy danych
//TODO: wyodrębnić zmienne dotyczące środowiska do pliku konfiguracyjnego
$db = new mysqli('localhost', 'root', '', 'bankAPI');
//ustawienie kodowania znaków na utf8 dla bazy danych
$db->set_charset('utf8');

//użyj przestrzeni nazw od klasy routingu i od naszej klasy od rachunków
use Steampixel\Route;
use BankAPI\Account;

//jeśli ktoś zapyta API bez żadnego parametru
//zwróć hello world
//TODO: to jest tylko do testów  - usunąć później
Route::add('/', function() {
  echo 'Hello world!';
});
//ścieżka używana przez aplikację okienkową do logowania
//aplikacja wysyła  nam login i hasło zakodowane JSON metodą post
//API odpowiada do aplikacji wysyłając token w formacie JSON
Route::add('/login', function() use($db) {
  //php nie potrafi odebrac JSONa w post tak jak formularza
  //więc musimy odczytać
  //dane z php input - tam znajdziemy JSONa
  $data = file_get_contents('php://input');
  $data = json_decode($data, true);
  $ip = $_SERVER['REMOTE_ADDR'];
  try {
    //spróbuj zalogować użytkownika
    $id = User::login($data['login'], $data['password'], $db);
    //wygeneruj nowy token dla tego użytkownika i tego IP
    $token = Token::new($ip, $id, $db);
    //ustaw nagłówek odpowiedzi na JSON żeby przeglądarka 
    //wiedziała jak interpretować dane
    header('Content-Type: application/json');
    //zwróć token w formacie JSON
    echo json_encode(['token' => $token]);
  } catch (Exception $e) {
    //jeżeli nie udało się zalogować to zwróć błąd
    header('HTTP/1.1 401 Unauthorized');
    //czy naprawdę musimy zwracać jakąś treść?
    echo json_encode(['error' => 'Invalid login or password']);
    return;
  }
  
}, 'post');

//metoda zwracająca do aplikacji szczegóły rachunku 
//metoda identyfikuje użytkownika na podstawie tokenu
//sprawdza w bazie i zwraca dane pierwszego znalezionego rachunku
Route::add('/account/details', function() use($db) {
    //zakładamy, że aplikacja przekazała nam token w postaci danych JSON
    //przeczytaj surowe dane wejściowe z PHP
    $data = file_get_contents('php://input');
    //przekształć JSON wejściowe w tablicę asocjacyjną
    $dataArray = json_decode($data, true);
    //zakładam, ze w paczce danych jest token pod nazwą "token"
    $token = $dataArray['token'];
    //sprawdz poprawność tokena
    if(!Token::check($token, $_SERVER['REMOTE_ADDR'], $db)) {
        //jeżeli token jest niepoprawny to zwróć błąd
        header('HTTP/1.1 401 Unauthorized');
        //opcjonalnie
        return json_encode(['error' => 'Invalid token']);
    }
    //pobierz id użytkownika na podstawie tokena
    $userId = Token::getUserId($token, $db);
    //wyciągamy numer rachunku i zwracamy go jako json
    $accountNo = Account::getAccountNo($userId, $db);
    $account = Account::getAccount($accountNo, $db);
    header('Content-Type: application/json');
    return json_encode($account->getArray());
}, 'post');

//ścieżka wyświetla dane dotyczące rachunku bankowego po jego numerze
//jeżeli ktoś zapyta API o /account/1234 to zwróci dane rachunku o numerze 1234
//klasa Route podstawia argumenty z URL (wyrażenie regularne) do funkcji
Route::add('/account/([0-9]*)', function($accountNo) use($db) {
    //$accountNo to numer rachunku, którego dane chcę pobrać z bazy
    //funkcja statyczna pobiera informacje o rachunku z bazy danych i tworzy obiekt rachunku
    $account = Account::getAccount($accountNo, $db);
    //ustaw nagłówek odpowiedzi na JSON żeby przeglądarka wiedziała jak interpretować dane
    header('Content-Type: application/json');
    //zwróć dane w formacie JSON korzystając z funkcji udostępniającej dane prywatne jako tablicę
    return json_encode($account->getArray());
});

//endpoint do wykonywania przelewów
Route::add('/transfer/new', function() use($db) {
    //zakładamy, że aplikacja przekazała nam token w postaci danych JSON
    //przeczytaj surowe dane wejściowe z PHP
    $data = file_get_contents('php://input');
    //przekształć JSON wejściowe w tablicę asocjacyjną
    $dataArray = json_decode($data, true);
    //zakładam, ze w paczce danych jest token pod nazwą "token"
    $token = $dataArray['token'];
    //sprawdz poprawność tokena
    if(!Token::check($token, $_SERVER['REMOTE_ADDR'], $db)) {
        //jeżeli token jest niepoprawny to zwróć błąd
        header('HTTP/1.1 401 Unauthorized');
        //opcjonalnie
        return json_encode(['error' => 'Invalid token']);
    }
    //TODO: sprawdz dane i wykonaj przelew
    $userId = Token::getUserId($token, $db);
    $source = Account::getAccountNo($userId, $db);
    $KwotaPrzelewu = Account::getAccount($source, $db)->getArray()['amount'];
    $target = $dataArray['target'];
    $Kwota = $dataArray['amount'];
    if ($Kwota <= 0) {
      header('HTTP/1.1 400 Bad Request');
      return json_encode(['error' => 'Kwota musi być dodatnia']);
    }

    if($KwotaPrzelewu < $amount) {
        header('HTTP/1.1 400 Bad Request');
        return json_encode(['error' => 'Brak środków na koncie']);
    }
    Transfer::new($source, $target, $Kwota, $db);
    header('Status: 200');
    return json_encode(['status' => 'OK']);
}, 'post');

//ta linijka musi być na końcu
//musi tu być nazwa folderu w którym "mieszka" API
Route::run('/bankAPI');

$db->close();
?>