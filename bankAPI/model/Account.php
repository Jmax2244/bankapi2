<?php
//klasa odpowiadająca tabli account w bazie danych
namespace BankAPI;
//przestrzeń nazw - bez tego nie możemy użyć bazy mimo, że dostajemy ją jako argument
use mysqli;

class Account {
    //hermetyzacja - ukrywamy zmienne przed innymi klasami
    private $accountNo;
    private $amount;
    private $name;

    //tworzy nowy obiekt klasy account na podstawie danych podanych jako argumenty
    public function __construct(int $accountNo, int $amount, string $name) {
        $this->accountNo = $accountNo;
        $this->amount = $amount;
        $this->name = $name;
    }
    //funkcja zwraca numer rachunku na podstawie podanego ID użytkownika
    public static function getAccountNo(int $userId, mysqli $db) : int {
        //zapytanie do bazy danych
        $sql = "SELECT accountNo FROM account WHERE user_id = ? LIMIT 1";
        //przygotuj zapytanie
        $query = $db->prepare($sql);
        //podmień znaki zapytania na zmienne
        $query->bind_param('i', $userId);
        //wykonaj zapytanie
        $query->execute();
        //pobierz wynik
        $result = $query->get_result();
        //nie robie pętli dlatego, że mamy LIMIT 1
        //skonwertuj na tablicę asocjacyjną
        $account = $result->fetch_assoc();
        //zwróć numer rachunku
        return $account['accountNo'];
    }

    //tworzy nowy obiekt na podstawie numeru rachunku pobierając dane z bazy
    //metoda statyczna - nie trzeba tworzyć obiektu klasy żeby jej użyć
    //zwraca obiekt klasy account
    public static function getAccount(int $accountNo, mysqli $db) : Account {
        //pobierz informacje o rachunku
        $result = $db->query("SELECT * FROM account WHERE accountNo = $accountNo");
        //skonwertuj na tablicę asocjacyjną
        $account = $result->fetch_assoc();
        $account = new Account($account['accountNo'], $account['amount'], $account['name']);
        return $account;
    }
    //zwraca tablicę z danymi obiektu to celów serializacji do JSON
    public function getArray() : array {
        $array = [
            'accountNo' => $this->accountNo,
            'amount' => $this->amount,
            'name' => $this->name
        ];
        return $array;
    }
}
?>