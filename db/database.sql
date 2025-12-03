-- =========================================================================
-- 1. डेटाबेस निर्माण
-- =========================================================================
CREATE DATABASE IF NOT EXISTS teen_patti CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE teen_patti;

-- =========================================================================
-- 2. ADMIN TABLE
-- व्यवस्थापक लॉगिन क्रेडेंशियल
-- =========================================================================
CREATE TABLE IF NOT EXISTS admin (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL COMMENT 'व्यवस्थापक लॉगिन उपयोगकर्ता नाम।',
    password VARCHAR(255) NOT NULL COMMENT 'सुरक्षित bcrypt हैशेड पासवर्ड।',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) COMMENT='वेब एडमिन पैनल एक्सेस के लिए खाते';

-- डिफ़ॉल्ट व्यवस्थापक प्रविष्टि (username: kaushal, password: 123456)
-- हैश: $2y$10$22nC6u6t4i9v0D5F7X1s8uXl8C5d7tQ0iO9L4jG0lT2mJp9Z1eA7m
INSERT INTO admin (username, password) VALUES (
    'kaushal',
    '$2y$10$22nC6u6t4i9v0D5F7X1s8uXl8C5d7tQ0iO9L4jG0lT2mJp9Z1eA7m'
);

-- =========================================================================
-- 3. USERS TABLE
-- उपयोगकर्ता विवरण और वित्तीय बैलेंस
-- =========================================================================
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT 'उपयोगकर्ता का पूरा नाम।',
    email VARCHAR(150) UNIQUE NOT NULL COMMENT 'उपयोगकर्ता का ईमेल (मुख्य पहचानकर्ता)।',
    password VARCHAR(255) NOT NULL COMMENT 'सुरक्षित bcrypt हैशेड पासवर्ड।',
    wallet_balance DECIMAL(10, 2) DEFAULT 0.00 NOT NULL COMMENT 'उपयोगकर्ता का वर्तमान वॉलेट बैलेंस।',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user_email (email)
) COMMENT='सिस्टम में पंजीकृत उपयोगकर्ता खाते';

-- =========================================================================
-- 4. TRANSACTIONS TABLE
-- सभी वित्तीय रिकॉर्ड (एडमिन डैशबोर्ड द्वारा उपयोग किए जाने वाले कमीशन और प्रोसेसर कॉलम शामिल हैं)
-- =========================================================================
-- यह वह अपडेटेड schema है जिसे मैंने 'Pending' DEFAULT के साथ ठीक किया था।
CREATE TABLE IF NOT EXISTS transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type ENUM('recharge','withdraw','bet','win', 'commission_credit') NOT NULL COMMENT 'लेन-देन का प्रकार।',
    amount DECIMAL(10, 2) NOT NULL COMMENT 'लेन-देन की राशि।',
    status ENUM('Completed', 'Pending', 'Failed', 'Cancelled', 'Rejected') DEFAULT 'Pending' NOT NULL COMMENT 'लेन-देन की वर्तमान स्थिति।',
    commission_percent DECIMAL(5, 2) DEFAULT 0.00 NOT NULL COMMENT 'लेनदेन पर लगाया गया कमीशन प्रतिशत।',
    commission_amount DECIMAL(10, 2) DEFAULT 0.00 NOT NULL COMMENT 'कमीशन की राशि।', 
    processed_by INT UNSIGNED NULL COMMENT 'एडमिन की ID जिसने प्रोसेस किया।',
    remark VARCHAR(255) NULL COMMENT 'टिप्पणी।',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES admin(id) ON DELETE SET NULL, 
    INDEX idx_transaction_user (user_id),
    INDEX idx_transaction_type_status (type, status),
    INDEX idx_transaction_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='सभी उपयोगकर्ता-संबंधित वित्तीय लेनदेन';

-- =========================================================================
-- 5. ADMIN COMMISSION LOG
-- सिस्टम द्वारा अर्जित राजस्व का ट्रैक (डैशबोर्ड स्टैट्स के लिए उपयोग किया जाता है)
-- =========================================================================
CREATE TABLE IF NOT EXISTS admin_commission (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT UNSIGNED NULL COMMENT 'यदि कमीशन किसी विशिष्ट लेनदेन से जुड़ा है।',
    source ENUM('recharge','bet_loss','game_fee') NOT NULL COMMENT 'कमीशन आय का स्रोत।', 
    amount DECIMAL(10, 2) NOT NULL COMMENT 'कमीशन की राशि।',
    calculated_percent DECIMAL(5, 2) DEFAULT 0.00 NOT NULL COMMENT 'कमीशन का प्रतिशत जिसके आधार पर यह राशि कैलकुलेट हुई।',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL,
    
    INDEX idx_commission_source (source)
) COMMENT='सिस्टम राजस्व और कमीशन लॉग';


-- =========================================================================
-- 6. GAME BETS
-- गेम राउंड के भीतर व्यक्तिगत बेट्स का इतिहास (गेम लॉजिक के लिए)
-- =========================================================================
CREATE TABLE IF NOT EXISTS game_bets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    round_id VARCHAR(50) NOT NULL COMMENT 'विशिष्ट गेम राउंड ID (उदा. टाइमस्टैम्प-आधारित)।',
    bet_amount DECIMAL(10, 2) NOT NULL COMMENT 'दांव पर लगाई गई राशि।',
    result ENUM('win','lose','fold') NOT NULL COMMENT 'बेट का परिणाम।',
    win_amount DECIMAL(10, 2) DEFAULT 0.00 NOT NULL COMMENT 'जीती गई शुद्ध राशि (0 अगर हार या फोल्ड)।',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    
    INDEX idx_bet_user (user_id),
    INDEX idx_bet_round (round_id)
) COMMENT='तीन पत्ती गेम राउंड में उपयोगकर्ता के दांव का इतिहास';


-- =========================================================================
-- 7. USER PAYOUT METHODS
-- उपयोगकर्ता के लिए एकाधिक निकासी विकल्प (सामान्यीकृत संरचना)
-- =========================================================================
CREATE TABLE IF NOT EXISTS user_payout_methods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    method_type ENUM('bank', 'upi') NOT NULL COMMENT 'भुगतान विधि का प्रकार।',
    account_holder VARCHAR(100) NOT NULL COMMENT 'खाता धारक का नाम।',
    bank_account VARCHAR(50) NULL COMMENT 'बैंक खाता संख्या (यदि बैंक विधि है)।',
    bank_ifsc VARCHAR(20) NULL COMMENT 'IFSC कोड (यदि बैंक विधि है)।',
    upi_id VARCHAR(50) NULL COMMENT 'UPI ID (यदि UPI विधि है)।',
    is_default BOOLEAN DEFAULT FALSE NOT NULL COMMENT 'क्या यह उपयोगकर्ता की डिफ़ॉल्ट निकासी विधि है।',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- सुनिश्चित करता है कि एक उपयोगकर्ता के लिए केवल एक डिफ़ॉल्ट विधि है
    UNIQUE KEY uc_user_default (user_id, is_default)
) COMMENT='उपयोगकर्ताओं द्वारा सहेजी गई निकासी विधियाँ';