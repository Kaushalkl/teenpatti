<?php
// Initialize session for state management
session_start();

/* ----------------------------------------------------
 * SIMULATED DB FETCH & UPDATE FUNCTIONS 
 * ----------------------------------------------------*/

/**
 * वास्तविक डेटाबेस से उपयोगकर्ता का वॉलेट बैलेंस प्राप्त करने का अनुकरण करता है।
 * (Simulates fetching the user's wallet balance from a real database.)
 * @param int $userId The ID of the user.
 * @return float The wallet balance.
 */
function fetchWalletBalanceFromDB($userId) {
    // ----------------------------------------------------------------------
    // NOTE: यहां आपको वास्तविक डेटाबेस क्वेरी (real database query) करनी होगी।
    // ----------------------------------------------------------------------
    
    // सिमुलेशन: यदि यह पहली बार है, तो ₹5000 लौटाएँ; अन्यथा, सत्र से वर्तमान मान लें।
    if (!isset($_SESSION['db_loaded_balance'])) {
        $_SESSION['db_loaded_balance'] ; 
    }
    return (float)$_SESSION['db_loaded_balance'];
}


/**
 * वास्तविक डेटाबेस में उपयोगकर्ता का बैलेंस अपडेट करने का अनुकरण करता है।
 * (Simulates updating the user's balance in a real database.)
 * * @param int $userId The ID of the user.
 * @param float $newBalance The new balance to set.
 * @return bool True on success, False on failure.
 */
function updateWalletBalanceInDB($userId, $newBalance) {
    // ----------------------------------------------------------------------
    // NOTE: यहां आपको वास्तविक डेटाबेस UPDATE क्वेरी करनी होगी।
    // ----------------------------------------------------------------------
    
    // सिमुलेशन: DB (सत्र) में नया बैलेंस सेट करें।
    $_SESSION['db_loaded_balance'] = $newBalance; 
    return true; // Assume success
}


/* ----------------------------------------------------
 * AUTH & WALLET SETUP
 * ----------------------------------------------------*/

if (!isset($_SESSION['is_logged_in'])) {
    // 1. उपयोगकर्ता को लॉग इन करें
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'Player 1';
    $_SESSION['is_logged_in'] = true;
    
    // 2. DB से वास्तविक बैलेंस लाएँ और उसे सेशन में सेट करें
    $initial_balance = fetchWalletBalanceFromDB($_SESSION['user_id']);
    $_SESSION['wallet_balance'] = $initial_balance; 
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name']; // Fetch user name

/* ----------------------------------------------------
 * MAIN WALLET TRANSACTION FUNCTION 
 * ----------------------------------------------------*/

/**
 * वॉलेट को अपडेट करता है और सिमुलेटेड DB में भी बदलाव करता है।
 * यह सुनिश्चित करता है कि दिखाई देने वाला और DB में संग्रहित बैलेंस हमेशा समान हो।
 * * @param float $amount The amount to add (positive) or deduct (negative).
 * @return float The new, updated wallet balance.
 */
function updateWallet($amount) {
    global $user_id; 
    
    // 1. वर्तमान बैलेंस प्राप्त करें
    $current_balance = floatval($_SESSION['wallet_balance'] ?? 0);
    
    // 2. नया बैलेंस कैलकुलेट करें (0 से नीचे नहीं जाएगा)
    $new_balance = max(0.0, $current_balance + $amount); 
    
    // 3. Session Update (तत्काल दिखने वाला)
    $_SESSION['wallet_balance'] = $new_balance;
    
    // 4. SIMULATED DB Update (असली DB में अपडेट करने के लिए इस फ़ंक्शन को बदलें)
    updateWalletBalanceInDB($user_id, $new_balance);
    
    // 5. नया बैलेंस लौटाएँ
    return $new_balance;
}

// Global variable access (यह वह वेरिएबल है जिसे आप पेज पर इस्तेमाल करेंगे)
// यह हमेशा $_SESSION['wallet_balance'] से वर्तमान और वास्तविक मान लेगा।
$wallet = (float)($_SESSION['wallet_balance'] ?? 0.0);

/* ----------------------------------------------------
 * EXAMPLE USAGE (आप इसे वास्तविक उपयोग से बदल सकते हैं)
 * ----------------------------------------------------*/

// उदाहरण: यदि URL में 'bet' या 'win' पैरामीटर है, तो बैलेंस अपडेट करें
if (isset($_GET['bet'])) {
    // ₹100 का दाँव लगाएं (Bet)
    $bet_amount = -100; 
    $new_balance = updateWallet($bet_amount);
} elseif (isset($_GET['win'])) {
    // ₹500 जीत जोड़ें (Win)
    $win_amount = 500; 
    $new_balance = updateWallet($win_amount);
} elseif (isset($_GET['reset'])) {
    // बैलेंस को शुरुआती मान पर रीसेट करें
    unset($_SESSION['db_loaded_balance']);
    unset($_SESSION['wallet_balance']);
    // पेज को रीलोड करने के लिए, जिससे सेटअप लॉजिक फिर से चलेगा
    header("Location: index.php"); 
    exit();
}
// $wallet को अंतिम अपडेटेड वैल्यू के साथ फिर से सेट करें ताकि वह डिस्प्ले हो सके
$wallet = (float)($_SESSION['wallet_balance'] ?? 0.0);

/* ----------------------------------------------------
 * DATA CLEANUP HELPER (FIXES __PHP_Incomplete_Class_Name)
 * ----------------------------------------------------*/
function cleanSessionData(array &$data) {
    // This converts any nested objects (including incomplete ones from session deserialization)
    // into simple associative arrays, which is crucial for card data integrity.
    $data = json_decode(json_encode($data), true);
}

// ----------------------------------------------------
//      APPLY CLEANUP ON SESSION CARDS ON EVERY LOAD
// ----------------------------------------------------
if(isset($_SESSION['player_cards'])) {
    cleanSessionData($_SESSION['player_cards']);
}
if(isset($_SESSION['dealer_cards'])) {
    cleanSessionData($_SESSION['dealer_cards']);
}


/* ----------------------------------------------------
 * HAND POWER ENGINE - Teen Patti Rules
 * ----------------------------------------------------*/
class HandPowerSimple {
    // Rank Map: 2->2, ..., A->14
    const RANK_MAP = ['2'=>2,'3'=>3,'4'=>4,'5'=>5,'6'=>6,'7'=>7,'8'=>8,'9'=>9,'10'=>10,'J'=>11,'Q'=>12,'K'=>13,'A'=>14];
    
    // Hand Ranks (5=highest, 1=lowest):
    const RANK_TRAIL = 5;       
    const RANK_PURE_SEQUENCE = 4;
    const RANK_SEQUENCE = 3;    
    const RANK_COLOR = 2;       
    const RANK_PAIR = 1.5;      
    const RANK_HIGH_CARD = 1;   

    // Helper to get the numerical ranks of cards, sorted descending
    private static function ranks($cards){
        $vals=[];
        foreach($cards as $c){
            if(is_object($c)) $c = (array)$c;
            if(!isset($c['rank'])) throw new InvalidArgumentException("Invalid card format in ranks()");
            $vals[] = self::RANK_MAP[$c['rank']];
        }
        rsort($vals); // Sort descending: [high, mid, low]
        return $vals;
    }

    // Checks for a standard straight (e.g., 5-4-3) or the wrap-around A-2-3 (14-3-2)
    private static function isStraight($r){
        return (($r[0]==$r[1]+1 && $r[1]==$r[2]+1) || ($r[0]==14 && $r[1]==3 && $r[2]==2));
    }

    // Translates the numerical rank ID into a readable string
    public static function getRankName($rank_id){
        switch ($rank_id) {
            case self::RANK_TRAIL: return "Trail (Trio)";
            case self::RANK_PURE_SEQUENCE: return "Pure Sequence (Straight Flush)";
            case self::RANK_SEQUENCE: return "Sequence (Straight)";
            case self::RANK_COLOR: return "Color (Flush)";
            case self::RANK_PAIR: return "Pair (Double)";
            case self::RANK_HIGH_CARD: default: return "High Card";
        }
    }

    public static function calc($cards){
        $r_desc = self::ranks($cards); 
        $suits = array_column($cards, 'suit');
        $unique_ranks = array_unique($r_desc);
        $count_unique = count($unique_ranks);
        $straight = self::isStraight($r_desc);
        $sameSuit = ($suits[0]==$suits[1] && $suits[1]==$suits[2]);
        
        // 1. Trail (Three of a Kind)
        if($count_unique == 1) return [self::RANK_TRAIL, $r_desc[0], $r_desc[0], $r_desc[0]]; 
        
        // 2. Pure Sequence (Straight Flush)
        if($sameSuit && $straight) {
            $high_rank = ($r_desc[0] == 14 && $r_desc[1] == 3) ? 3 : $r_desc[0];
            return [self::RANK_PURE_SEQUENCE, $high_rank, $r_desc[1], $r_desc[2]]; 
        }
        
        // 3. Sequence (Straight)
        if($straight) {
            $high_rank = ($r_desc[0] == 14 && $r_desc[1] == 3) ? 3 : $r_desc[0];
            return [self::RANK_SEQUENCE, $high_rank, $r_desc[1], $r_desc[2]]; 
        }
        
        // 4. Color (Flush)
        if($sameSuit) return [self::RANK_COLOR, $r_desc[0], $r_desc[1], $r_desc[2]]; 
        
        // 5. Pair
        if($count_unique == 2) {
            $counts = array_count_values($r_desc);
            $pair_rank = 0;
            $kicker_rank = 0;
            foreach ($counts as $rank => $count) {
                if ($count == 2) $pair_rank = $rank;
                if ($count == 1) $kicker_rank = $rank;
            }
            return [self::RANK_PAIR, $pair_rank, $kicker_rank, 0]; 
        }
        
        // 6. High Card
        return [self::RANK_HIGH_CARD, $r_desc[0], $r_desc[1], $r_desc[2]];
    }

    // Compares two calculated hand arrays ($p and $d)
    public static function compareHands($p, $d){
        $p_rank_id = $p[0];
        $d_rank_id = $d[0];

        if ($p_rank_id > $d_rank_id) return 'player';
        if ($d_rank_id > $p_rank_id) return 'dealer';

        // Tie in hand rank (e.g., both have Pair). Compare by kicker cards.
        for ($i = 1; $i < count($p); $i++) {
            if (!isset($d[$i])) continue;
            
            if ($p[$i] > $d[$i]) return 'player';
            if ($d[$i] > $p[$i]) return 'dealer'; 
        }

        return 'tie';
    }
}

/* ----------------------------------------------------
 * DECK & DEALING LOGIC
 * ----------------------------------------------------*/
function createDeck(){
    $suits=['♠','♥','♦','♣'];
    $ranks=['2','3','4','5','6','7','8','9','10','J','Q','K','A'];
    $deck=[];
    foreach($suits as $s){
        foreach($ranks as $r) $deck[]=['rank'=>$r,'suit'=>$s];
    }
    shuffle($deck);
    return $deck;
}

function dealCards(){
    if(!isset($_SESSION['deck']) || count($_SESSION['deck']) < 6){
        $_SESSION['deck'] = createDeck();
    }
    $_SESSION['player_cards']=array_splice($_SESSION['deck'],0,3);
    $_SESSION['dealer_cards']=array_splice($_SESSION['deck'],0,3);
    $_SESSION['pot']=0;
    $_SESSION['player_bet']=0;
    $_SESSION['result']='';
    $_SESSION['player_hand_name'] = '';
    $_SESSION['dealer_hand_name'] = '';
    $_SESSION['msg']='Place your first bet to start the round!';
}

if(!isset($_SESSION['player_cards'])) {
    dealCards();
}

/* ----------------------------------------------------
 * GAME RESOLUTION LOGIC
 * ----------------------------------------------------*/
function resolveGame(){
    cleanSessionData($_SESSION['player_cards']);
    cleanSessionData($_SESSION['dealer_cards']);
    
    $player=$_SESSION['player_cards'];
    $dealer=$_SESSION['dealer_cards'];

    $p=HandPowerSimple::calc($player);
    $d=HandPowerSimple::calc($dealer);
    
    $_SESSION['player_hand_name'] = HandPowerSimple::getRankName($p[0]);
    $_SESSION['dealer_hand_name'] = HandPowerSimple::getRankName($d[0]);

    $winner=HandPowerSimple::compareHands($p, $d);

    $pot=$_SESSION['pot'];
    $player_bet = $_SESSION['player_bet'];

    if($winner=='player') {
        updateWallet($pot);
        $result_msg = "🥳 You WON ₹" . number_format($pot, 2) . "! (Your Hand: {$_SESSION['player_hand_name']})";
    } elseif($winner=='dealer') {
        $result_msg = "😔 Dealer Wins! You lost ₹" . number_format($player_bet, 2) . ". (Dealer's Hand: {$_SESSION['dealer_hand_name']})";
    } else {
        updateWallet($player_bet);
        $result_msg = "🤝 It's a Tie! Your bet of ₹" . number_format($player_bet, 2) . " is returned. (Both: {$_SESSION['player_hand_name']})";
    }

    $_SESSION['result']=$winner;
    $_SESSION['msg']=$result_msg;
    return $winner;
}

/* ----------------------------------------------------
 * AJAX HANDLER (JSON RESPONSE)
 * ----------------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $status = 'success';
    $msg = $_SESSION['msg'] ?? '';

  // Reload state for operations, ensuring $wallet is the current float value
$wallet = (float)($_SESSION['wallet_balance'] ?? 0.0);
$player_bet = $_SESSION['player_bet'] ?? 0;
$result = $_SESSION['result'] ?? '';
    try {
        if ($action === 'bet' || $action === 'raise') {
            $amount = (int)($_POST['amount'] ?? 0);
            
            if ($action === 'bet') {
                if ($player_bet > 0) throw new Exception("Action not allowed. The round has started. Use 'Raise'.");
                if ($amount < 10) throw new Exception("🚫 Minimum starting bet ₹10.");
                if ($amount > $wallet) throw new Exception("🚫 Insufficient balance. You only have ₹" . number_format($wallet, 2) . ".");
                
                $_SESSION['player_bet'] = $amount;
                $_SESSION['pot'] = $amount * 2;
                updateWallet(-$amount); // Deduct initial bet
                $msg = "Bet placed: ₹" . number_format($amount, 2) . ". Pot is now ₹" . number_format($_SESSION['pot'], 2) . ".";

            } else { // raise
                if ($player_bet === 0) throw new Exception("Place an initial bet first.");
                if ($amount < 10) throw new Exception("🚫 Minimum raise ₹10.");
                if ($amount > $wallet) throw new Exception("🚫 Not enough balance to raise. You only have ₹" . number_format($wallet, 2) . ".");
                
                $_SESSION['player_bet'] += $amount;
                $_SESSION['pot'] += $amount * 2;
                updateWallet(-$amount); // Deduct the raise amount
                $msg = "You raised ₹" . number_format($amount, 2) . ". Pot is now ₹" . number_format($_SESSION['pot'], 2) . ".";
            }
            $player_bet = $_SESSION['player_bet'];
            $wallet = (float)$_SESSION['wallet_balance'];
            $_SESSION['result'] = '';
            $_SESSION['player_hand_name'] = '';
            $_SESSION['dealer_hand_name'] = '';


        } elseif ($action === 'show') {
            if ($player_bet === 0) throw new Exception("Place a bet before showing cards.");
            
            $winner = resolveGame();
            $result = $_SESSION['result'];
            $msg = $_SESSION['msg'];
            $wallet = (float)$_SESSION['wallet_balance'];

        } elseif ($action === 'reset') {
            dealCards(); 
            $msg = $_SESSION['msg'];
            $wallet = (float)$_SESSION['wallet_balance'];
            $player_bet = $_SESSION['player_bet'];
            $result = $_SESSION['result'];
        }

    } catch (Exception $e) {
        $status = 'error';
        $msg = $e->getMessage();
    }

    // Collect current state to return as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $status,
        'msg' => $msg,
        // FIX: Ensure wallet is the latest float value from session before formatting
        'wallet' => number_format((float)$_SESSION['wallet_balance'], 2), 
        'pot' => number_format($_SESSION['pot'] ?? 0, 2),
        'player_bet' => number_format($_SESSION['player_bet'] ?? 0, 2),
        'result' => $_SESSION['result'] ?? '',
        'player_hand_name' => $_SESSION['player_hand_name'] ?? '',
        'dealer_hand_name' => $_SESSION['dealer_hand_name'] ?? '',
        'playerCards' => $_SESSION['player_cards'],
        'dealerCards' => $_SESSION['dealer_cards'],
    ]);
    exit;
}

/* ----------------------------------------------------
 * HTML RENDERING (Initial Page Load)
 * ----------------------------------------------------*/
$pot = $_SESSION['pot'] ?? 0;
$player_bet = $_SESSION['player_bet'] ?? 0;
$result = $_SESSION['result'] ?? '';
$initial_msg = $_SESSION['msg'] ?? 'Place your first bet to start the round!';
$user_name = $_SESSION['user_name'] ?? 'Guest';
$player_hand_name = $_SESSION['player_hand_name'] ?? '';
$dealer_hand_name = $_SESSION['dealer_hand_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teen Patti Royale Animated</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap');
        body { 
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        }

        /* --- CARD STYLING & ANIMATIONS --- */
        .card-container {
            perspective: 500px;
            width: 90px;
            height: 120px;
            cursor: pointer;
            transition: transform 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        @media (min-width: 640px) {
            .card-container {
                width: 100px;
                height: 140px;
            }
        }
        
        .card-inner {
            position: relative;
            width: 100%;
            height: 100%;
            text-align: center;
            transition: transform 0.6s;
            transform-style: preserve-3d;
        }

        /* Player cards start flipped (face up) */
        .player-card .card-inner {
            transform: rotateY(180deg);
        }
        
        /* Dealer cards start unflipped (back up) */
        .dealer-card .card-inner {
            transform: rotateY(0deg); 
        }

        /* The actual 3D flip effect on reveal */
        .dealer-cards-container.revealed .card-inner {
            transform: rotateY(180deg);
        }


        .card-face, .card-back {
            position: absolute;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            border-radius: 10px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.5);
        }

        /* Card Face (Front) */
        .card-face {
            background-color: white;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            padding: 5px;
            border: 2px solid #334155;
        }
        /* Face side is visible when rotated 180deg */
        .card-face {
            transform: rotateY(180deg); 
        }

        .red-suit { color: #dc2626; } /* Red for hearts/diamonds */
        .black-suit { color: #1f2937; } /* Dark Gray for spades/clubs */

        /* Card Back */
        .card-back {
            background: radial-gradient(circle, #0e7490 0%, #155e75 100%);
            transform: rotateY(0deg); /* Back side is visible by default (0deg) */
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 20px;
            color: #fcd34d;
            border: 4px solid #fcd34d;
            font-weight: 900;
        }
        
        /* Animation for card placement on reset/deal */
        .card-container.deal-0 { animation: slideIn 0.5s ease-out; }
        .card-container.deal-1 { animation: slideIn 0.5s ease-out 0.1s forwards; opacity: 0; }
        .card-container.deal-2 { animation: slideIn 0.5s ease-out 0.2s forwards; opacity: 0; }

        @keyframes slideIn {
            0% { transform: translateY(-50px) scale(0.8); opacity: 0; }
            100% { transform: translateY(0) scale(1); opacity: 1; }
        }

        /* --- BUTTON STYLING --- */
        .action-button {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 700;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -2px rgba(0, 0, 0, 0.3);
            border: none;
        }
        .action-button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 10px rgba(0, 0, 0, 0.4);
        }
        .action-button:disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }

        .bet-btn {
            background-color: #fcd34d; /* Gold/Yellow */
            color: #1f2937;
        }
        .show-btn {
            background-color: #ef4444; /* Red */
            color: white;
        }
        .reset-btn {
            background-color: #4b5563; /* Gray */
            color: white;
        }
        
        /* --- INFO BAR ANIMATIONS --- */
        @keyframes bounceIn {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }
        .win-bounce {
            animation: bounceIn 0.8s;
        }

    </style>
</head>
<body class="min-h-screen flex flex-col items-center p-4">

    <div class="max-w-xl w-full bg-gray-900/90 backdrop-blur-sm border border-gray-700 p-6 md:p-10 rounded-2xl shadow-2xl space-y-8">
        <header class="text-center space-y-3">
            <h1 class="text-5xl font-extrabold text-yellow-400 tracking-wider">Teen Patti Royale</h1>
            
            <div class="text-xl font-bold text-gray-200">
                Welcome, <span class="text-green-400"><?= htmlspecialchars($user_name) ?></span>!
            </div>
                        
            <div class="flex justify-between items-center bg-gray-800 p-3 rounded-xl shadow-inner border border-gray-700">
                <div class="text-left">
                    <p class="text-sm text-gray-400 font-medium">Wallet Balance (वॉलेट बैलेंस)</p>
                    <p id="wallet_balance" class="text-2xl font-bold text-green-400 win-bounce">₹<?= number_format($wallet, 2) ?></p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-400 font-medium">Current Pot (वर्तमान पॉट)</p>
                    <p id="pot_amount" class="text-3xl font-extrabold text-yellow-300">₹<?= number_format($pot, 2) ?></p>
                </div>
            </div>
        </header>

        <div id="game_message" class="text-center p-4 rounded-xl font-semibold transition-all duration-500 text-lg 
            <?= $result ? 'bg-indigo-900/70 border-2 border-indigo-400 shadow-lg shadow-indigo-900/50' : 'bg-gray-700/50 border border-gray-600' ?>">
            <?= $initial_msg ?>
        </div>
        
        <div id="hand_rank_info" class="text-center text-sm font-medium text-gray-300 transition-opacity duration-500 <?= $result ? 'opacity-100' : 'opacity-0 h-0' ?> overflow-hidden">
            <?php if($result): ?>
                <p>Dealer Hand: <span class="text-red-300"><?= htmlspecialchars($dealer_hand_name) ?></span> | Your Hand: <span class="text-green-300"><?= htmlspecialchars($player_hand_name) ?></span></p>
            <?php endif; ?>
        </div>

        <section class="space-y-4">
            <h2 class="text-xl font-bold text-center text-red-400">Dealer's Hand (डीलर के कार्ड)</h2>
            <div id="dealer_cards" class="dealer-cards-container flex justify-center space-x-3 md:space-x-4 <?= $result ? 'revealed' : '' ?>">
                </div>
        </section>

        <section class="space-y-4 pt-6">
            <h2 class="text-xl font-bold text-center text-green-400">Your Hand (आपके कार्ड)</h2>
            <div id="player_cards" class="flex justify-center space-x-3 md:space-x-4">
                </div>
        </section>

        <div id="game_controls" class="space-y-6 pt-8">

            <form id="bet_form" class="flex flex-col md:flex-row gap-3 justify-center items-center">
                <input type="number" id="bet_amount" name="amount" min="10" value="<?= $player_bet > 0 ? 10 : 50 ?>" 
                        class="w-full md:w-40 p-3 rounded-lg bg-gray-700 text-white border border-gray-600 focus:ring-yellow-500 focus:border-yellow-500 shadow-md" 
                        placeholder="राशि (₹)" required>
                
                <button type="submit" data-action="bet" id="bet_button"
                        class="action-button bet-btn w-full md:w-64">
                    <?= $player_bet > 0 ? 'Raise (बढ़ाएँ)' : 'Place Bet (बेट लगाएँ)' ?> (Current Bet: ₹<?= number_format($player_bet, 2) ?>)
                </button>
            </form>

            <div class="flex justify-center gap-4">
                <button id="show_button" data-action="show"
                        class="action-button show-btn w-32 md:w-36" disabled>
                    Show Cards (कार्ड दिखाएँ)
                </button>
                <button id="reset_button" data-action="reset"
                        class="action-button reset-btn w-32 md:w-36">
                    Play Again (दोबारा खेलें)
                </button>
            </div>
        </div>
    </div>

    <script>
        
        // Initial data from PHP on page load
        const initialPlayerCards = <?= json_encode($_SESSION['player_cards'] ?? []) ?>;
        const initialDealerCards = <?= json_encode($_SESSION['dealer_cards'] ?? []) ?>;
        const initialResult = "<?= $result ?>";
        const initialBet = parseFloat("<?= $player_bet ?>");
        const initialPlayerHandName = "<?= $player_hand_name ?>";
        const initialDealerHandName = "<?= $dealer_hand_name ?>";


        // Function to generate the HTML for a single card with animation class
        function getCardHTML(card, isPlayer, index) {
            const isRed = card.suit === '♥' || card.suit === '♦';
            const suitClass = isRed ? 'red-suit' : 'black-suit';
            const cardClass = isPlayer ? 'player-card' : 'dealer-card';
            
            // Handle potential Unicode escape sequences
            let suitChar = card.suit;
            try {
                if (card.suit.startsWith('\\u')) {
                    suitChar = String.fromCodePoint(parseInt(card.suit.substring(2), 16));
                }
            } catch(e) {}

            return `
                <div class="card-container ${cardClass} deal-${index}">
                    <div class="card-inner">
                        <div class="card-back">
                            <span class="text-2xl font-extrabold">TP</span>
                        </div>
                        <div class="card-face">
                            <span class="text-xl font-bold self-start ${suitClass}">${card.rank}</span>
                            <span class="text-5xl font-extrabold ${suitClass}">${suitChar}</span>
                            <span class="text-xl font-bold self-end transform rotate-180 ${suitClass}">${card.rank}</span>
                        </div>
                    </div>
                </div>
            `;
        }

        // Utility function to update the UI from JSON response
        function updateUI(data, isReset = false) {
            const walletEl = document.getElementById('wallet_balance');
            const potEl = document.getElementById('pot_amount');
            const msgEl = document.getElementById('game_message');
            const rankEl = document.getElementById('hand_rank_info');
            const betBtn = document.getElementById('bet_button');
            const betInput = document.getElementById('bet_amount');
            const showBtn = document.getElementById('show_button');
            const dealerContainer = document.getElementById('dealer_cards');
            const playerContainer = document.getElementById('player_cards');
            
            // FIX: Ensure correct parsing of comma-separated numbers from PHP
            const playerBet = parseFloat(data.player_bet.replace(/,/g, '')); 
            const isBetPlaced = playerBet > 0;
            const isRoundOver = data.result !== '';

            // 1. Update primary stats
            walletEl.textContent = `₹${data.wallet}`;
            potEl.textContent = `₹${data.pot}`;
            
            // Win/loss visual feedback
            walletEl.classList.remove('win-bounce');
            if (data.status !== 'error' && isRoundOver && data.result === 'player') {
                 setTimeout(() => walletEl.classList.add('win-bounce'), 100);
            }
            
            // 2. Update message
            msgEl.textContent = data.msg;
            msgEl.classList.remove('bg-red-900/70', 'bg-green-900/70', 'bg-indigo-900/70', 'border-red-400', 'border-green-400', 'border-indigo-400', 'bg-gray-700/50');
            
            let messageClass = 'bg-gray-700/50 border border-gray-600';
            if (data.status === 'error') {
                messageClass = 'bg-red-900/70 border-2 border-red-400 shadow-lg shadow-red-900/50';
            } else if (isRoundOver) {
                if (data.result === 'player') {
                    messageClass = 'bg-green-900/70 border-2 border-green-400 shadow-lg shadow-green-900/50';
                } else if (data.result === 'dealer') {
                    messageClass = 'bg-red-900/70 border-2 border-red-400 shadow-lg shadow-red-900/50';
                } else {
                    messageClass = 'bg-indigo-900/70 border-2 border-indigo-400 shadow-lg shadow-indigo-900/50';
                }
            } else if (isBetPlaced) {
                messageClass = 'bg-indigo-900/70 border-2 border-indigo-400 shadow-lg shadow-indigo-900/50';
            }
            msgEl.className = `text-center p-4 rounded-xl font-semibold transition-all duration-500 text-lg ${messageClass}`;

            // 3. Update Hand Rank Info
            if (isRoundOver) {
                rankEl.innerHTML = `<p>Dealer Hand: <span class="text-red-300">${data.dealer_hand_name}</span> | Your Hand: <span class="text-green-300">${data.player_hand_name}</span></p>`;
                rankEl.classList.remove('opacity-0', 'h-0');
            } else {
                rankEl.classList.add('opacity-0', 'h-0');
            }


            // 4. Update controls
            betInput.value = isBetPlaced ? 10 : 50;
            betBtn.textContent = isBetPlaced ? `Raise (बढ़ाएँ) (Current Bet: ₹${data.player_bet})` : 'Place Bet (बेट लगाएँ)';
            betBtn.setAttribute('data-action', isBetPlaced ? 'raise' : 'bet');
            
            betBtn.disabled = isRoundOver;
            showBtn.disabled = isRoundOver || !isBetPlaced;
            betInput.disabled = isRoundOver;


            // 5. Update Cards & Animations
            
            const renderCards = (cards, isPlayer) => {
                return cards.map((c, i) => getCardHTML(c, isPlayer, i)).join('');
            };

            if (isReset) {
                playerContainer.innerHTML = '';
                dealerContainer.innerHTML = '';
                dealerContainer.classList.remove('revealed');
                
                setTimeout(() => {
                    playerContainer.innerHTML = renderCards(data.playerCards, true);
                    dealerContainer.innerHTML = renderCards(data.dealerCards, false);
                }, 50);

            } else if (isRoundOver) {
                if (dealerContainer.innerHTML === '') {
                    dealerContainer.innerHTML = renderCards(data.dealerCards, false);
                }
                dealerContainer.classList.add('revealed');
            }
            
            if (!isReset && !isRoundOver) {
                 playerContainer.innerHTML = renderCards(data.playerCards, true);
                 dealerContainer.innerHTML = renderCards(data.dealerCards, false);
                 dealerContainer.classList.remove('revealed');
            }
        }
        
        // Main fetch handler with exponential backoff
        async function sendAction(action, amount = 0) {
            const formData = new FormData();
            formData.append('action', action);
            if (amount) {
                formData.append('amount', amount);
            }
            
            const maxRetries = 3;
            let lastError = null;
            let isReset = (action === 'reset');

            document.querySelectorAll('.action-button').forEach(btn => btn.disabled = true);

            for (let attempt = 0; attempt < maxRetries; attempt++) {
                try {
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();
                    
                    updateUI(data, isReset);
                    return; // Success, exit loop
                } catch (error) {
                    lastError = error;
                    const delay = Math.pow(2, attempt) * 1000;
                    if (attempt < maxRetries - 1) {
                        await new Promise(resolve => setTimeout(resolve, delay));
                    }
                }
            }
            
            // If all retries fail, display the last error and re-enable buttons
            const errorData = {
                status: 'error',
                msg: `Failed to complete action after ${maxRetries} attempts: ${lastError.message}`,
                // FIX: Robustly retrieve current wallet balance from display for error state
                wallet: document.getElementById('wallet_balance').textContent.replace(/[₹,]/g, ''), 
                pot: document.getElementById('pot_amount').textContent.replace(/[₹,]/g, ''),
                player_bet: document.getElementById('bet_button').textContent.match(/₹([\d,.]+)/)?.[1] || '0.00',
                result: '', playerCards: [], dealerCards: []
            };
            updateUI(errorData, false);
            document.querySelectorAll('.action-button').forEach(btn => btn.disabled = false);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('bet_form');
            const showButton = document.getElementById('show_button');
            const resetButton = document.getElementById('reset_button');
            
            // Initial render of cards and state using PHP data
            updateUI({
                status: 'success', 
                msg: "<?= $initial_msg ?>", 
                wallet: "<?= number_format($wallet, 2) ?>",
                pot: "<?= number_format($pot, 2) ?>",
                player_bet: "<?= number_format($player_bet, 2) ?>",
                result: "<?= $result ?>",
                player_hand_name: initialPlayerHandName,
                dealer_hand_name: initialDealerHandName,
                playerCards: initialPlayerCards,
                dealerCards: initialDealerCards
            }, true); 

            // Set initial show button state
            showButton.disabled = initialResult !== '' || initialBet === 0;

            // Handle Bet/Raise Form Submission
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const betButton = document.getElementById('bet_button');
                const action = betButton.getAttribute('data-action');
                const amount = document.getElementById('bet_amount').value;
                if(parseFloat(amount) > 0) {
                     sendAction(action, amount);
                }
            });

            // Handle Show Cards
            showButton.addEventListener('click', function() {
                sendAction('show');
            });

            // Handle Reset Game
            resetButton.addEventListener('click', function() {
                sendAction('reset');
            });
        });
    </script>
</body>
</html>