
<?php
namespace BankAPI;

class Transfer {
    public static function process($data, $db) {
        $token = $data['token'];
        $targetAccountNo = $data['target'];
        $transferAmount = $data['amount'];

        if ($transferAmount <= 0) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Transfer amount must be greater than zero.']);
            return false;
        }

        if (!Token::check($token, $_SERVER['REMOTE_ADDR'], $db)) {
            header('HTTP/1.1 401 Unauthorized');
            echo json_encode(['error' => 'Invalid token.']);
            return false;
        }

        $userId = Token::getUserId($token, $db);
        $accountNo = Account::getAccountNo($userId, $db);
        $account = Account::getAccount($accountNo, $db);

        if ($account->getBalance() < $transferAmount) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Insufficient balance for this transfer.']);
            return false;
        }

        // Deduct amount from source account and add to target account
        $account->deductBalance($transferAmount, $db);
        $targetAccount = Account::getAccount($targetAccountNo, $db);
        $targetAccount->addBalance($transferAmount, $db);

        return true;
    }
}
?>
