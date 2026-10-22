<?php
/**
 * Farmogram Database Seeder
 * Populates tables with comprehensive demo data for presentation.
 */

// CLI check to prevent execution from browser
if (php_sapi_name() !== 'cli') {
    die("Security Error: This script can only be run from the command line.");
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once __DIR__ . '/../config/db.php';

echo "Starting Farmogram Database Seeder...\n";

// Disable foreign key checks for truncation
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

$tables = [
    'reviews',
    'order_items',
    'orders',
    'farm_tasks',
    'enquiry_messages',
    'enquiries',
    'crop_claims',
    'crops',
    'notifications',
    'users'
];

foreach ($tables as $table) {
    if ($conn->query("TRUNCATE TABLE `$table`")) {
        echo "Truncated table: $table\n";
    } else {
        echo "Error truncating $table: " . $conn->error . "\n";
    }
}

$conn->query("SET FOREIGN_KEY_CHECKS = 1");

// Helper to hash passwords
$password_hash = password_hash('password123', PASSWORD_BCRYPT);

// --- 1. SEED USERS ---
$users = [
    [
        'id' => 1,
        'name' => 'System Admin',
        'email' => 'admin@farmogram.com',
        'phone' => '1000000000',
        'password' => $password_hash,
        'role' => 'admin',
        'location' => 'Delhi HQ',
        'badge' => 'none',
        'verification_status' => 'verified',
        'bio' => 'Farmogram platform administrator.',
        'profile_image' => 'admin.jpg'
    ],
    [
        'id' => 2,
        'name' => 'Farmer Ajay',
        'email' => 'ajay@farmer.com',
        'phone' => '9876543210',
        'password' => $password_hash,
        'role' => 'farmer',
        'location' => 'Punjab',
        'badge' => 'verified',
        'verification_status' => 'verified',
        'bio' => 'Specialist in organic wheat and basmati rice farming from Jalandhar, Punjab.',
        'profile_image' => 'farmer_ajay.jpg'
    ],
    [
        'id' => 3,
        'name' => 'Buyer Raju',
        'email' => 'raju@buyer.com',
        'phone' => '9876543211',
        'password' => $password_hash,
        'role' => 'buyer',
        'location' => 'Delhi NCR',
        'badge' => 'none',
        'verification_status' => 'verified',
        'bio' => 'Wholesale organic vegetable supplier and distributor in North India.',
        'profile_image' => 'buyer_raju.jpg'
    ],
    [
        'id' => 4,
        'name' => 'Farmer Suresh',
        'email' => 'suresh@farmer.com',
        'phone' => '9876543212',
        'password' => $password_hash,
        'role' => 'farmer',
        'location' => 'Haryana',
        'badge' => 'none',
        'verification_status' => 'pending',
        'bio' => 'Hydroponic potato and carrot farmer focusing on pesticide-free techniques in Karnal, Haryana.',
        'profile_image' => 'farmer_suresh.jpg',
        'identity_doc' => 'id_4_doc.jpg',
        'verification_doc' => 'land_4_doc.jpg',
        'verification_submitted_at' => date('Y-m-d H:i:s', strtotime('-1 days'))
    ],
    [
        'id' => 5,
        'name' => 'Farmer Ramesh',
        'email' => 'ramesh@farmer.com',
        'phone' => '9876543213',
        'password' => $password_hash,
        'role' => 'farmer',
        'location' => 'Rajasthan',
        'badge' => 'none',
        'verification_status' => 'unverified',
        'bio' => 'Traditional dryland cultivator growing millets, bajra, and cotton in Jodhpur, Rajasthan.',
        'profile_image' => 'farmer_ramesh.jpg'
    ],
    [
        'id' => 6,
        'name' => 'Buyer Priya',
        'email' => 'priya@buyer.com',
        'phone' => '9876543214',
        'password' => $password_hash,
        'role' => 'buyer',
        'location' => 'Mumbai',
        'badge' => 'none',
        'verification_status' => 'unverified',
        'bio' => 'Procurement head for GreenBasket retail supermarket chains.',
        'profile_image' => 'buyer_priya.jpg'
    ],
    [
        'id' => 7,
        'name' => 'Buyer Vikram',
        'email' => 'vikram@buyer.com',
        'phone' => '9876543215',
        'password' => $password_hash,
        'role' => 'buyer',
        'location' => 'Bangalore',
        'badge' => 'none',
        'verification_status' => 'unverified',
        'bio' => 'Owner of The Organic Table farm-to-fork restaurant network.',
        'profile_image' => 'buyer_vikram.jpg'
    ]
];

foreach ($users as $u) {
    $stmt = $conn->prepare("INSERT INTO users (id, name, email, phone, password, role, location, badge, verification_status, bio, profile_image, verification_doc, identity_doc, verification_submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $ver_doc = $u['verification_doc'] ?? null;
    $id_doc = $u['identity_doc'] ?? null;
    $submitted = $u['verification_submitted_at'] ?? null;
    
    $stmt->bind_param(
        "isssssssssssss",
        $u['id'],
        $u['name'],
        $u['email'],
        $u['phone'],
        $u['password'],
        $u['role'],
        $u['location'],
        $u['badge'],
        $u['verification_status'],
        $u['bio'],
        $u['profile_image'],
        $ver_doc,
        $id_doc,
        $submitted
    );
    
    if ($stmt->execute()) {
        echo "Seeded user: {$u['name']} (Role: {$u['role']})\n";
    } else {
        echo "Failed to seed user {$u['name']}: " . $stmt->error . "\n";
    }
}


// --- 2. SEED CROPS ---
$crops = [
    [
        'id' => 1,
        'farmer_id' => 2, // Ajay
        'crop_name' => 'Wheat (Premium Kalyan Sona)',
        'sowing_date' => '2026-02-15',
        'expected_harvest' => '2026-06-15',
        'actual_yield' => null,
        'investment' => 12000.00,
        'market_price' => 25.00,
        'image' => 'crop_1776868428_553.jpg',
        'predicted_yield' => 652.77,
        'quantity' => 100,
        'status' => 'growing',
        'acreage' => 5.00
    ],
    [
        'id' => 2,
        'farmer_id' => 2, // Ajay
        'crop_name' => 'Tomato (Hybrid Roma)',
        'sowing_date' => '2026-03-01',
        'expected_harvest' => '2026-05-01',
        'actual_yield' => null,
        'investment' => 5000.00,
        'market_price' => 30.50,
        'image' => 'crop_1776868512_910.jpg',
        'predicted_yield' => 291.57,
        'quantity' => 50,
        'status' => 'for_sale',
        'acreage' => 2.50
    ],
    [
        'id' => 3,
        'farmer_id' => 2, // Ajay
        'crop_name' => 'Basmati Rice (Pusa 1121)',
        'sowing_date' => '2026-05-01',
        'expected_harvest' => '2026-09-01',
        'actual_yield' => null,
        'investment' => 25000.00,
        'market_price' => 65.00,
        'image' => 'crop_1772197451_304.jpg',
        'predicted_yield' => 1200.00,
        'quantity' => 200,
        'status' => 'growing',
        'acreage' => 10.00
    ],
    [
        'id' => 4,
        'farmer_id' => 2, // Ajay
        'crop_name' => 'Red Onion (Nasik)',
        'sowing_date' => '2026-01-10',
        'expected_harvest' => '2026-04-10',
        'actual_yield' => 495.00,
        'investment' => 8000.00,
        'market_price' => 45.00,
        'image' => 'crop_1776868211_684.png',
        'predicted_yield' => 481.57,
        'quantity' => 0,
        'status' => 'harvested',
        'acreage' => 3.00
    ],
    [
        'id' => 5,
        'farmer_id' => 4, // Suresh
        'crop_name' => 'Organic Barley',
        'sowing_date' => '2026-03-10',
        'expected_harvest' => '2026-07-10',
        'actual_yield' => null,
        'investment' => 14000.00,
        'market_price' => 28.00,
        'image' => 'crop_1772288830_646.jpg',
        'predicted_yield' => 820.00,
        'quantity' => 150,
        'status' => 'growing',
        'acreage' => 6.00
    ],
    [
        'id' => 6,
        'farmer_id' => 4, // Suresh
        'crop_name' => 'Russet Potato',
        'sowing_date' => '2026-02-20',
        'expected_harvest' => '2026-05-20',
        'actual_yield' => null,
        'investment' => 10000.00,
        'market_price' => 18.00,
        'image' => 'crop_1772864784_709.jpg',
        'predicted_yield' => 1500.00,
        'quantity' => 300,
        'status' => 'for_sale',
        'acreage' => 4.00
    ],
    [
        'id' => 7,
        'farmer_id' => 4, // Suresh
        'crop_name' => 'Yellow Mustard',
        'sowing_date' => '2025-11-01',
        'expected_harvest' => '2026-03-01',
        'actual_yield' => 420.00,
        'investment' => 7500.00,
        'market_price' => 55.00,
        'image' => 'crop_1774354224_674.jpg',
        'predicted_yield' => 400.00,
        'quantity' => 0,
        'status' => 'archived',
        'acreage' => 3.50
    ],
    [
        'id' => 8,
        'farmer_id' => 5, // Ramesh
        'crop_name' => 'Bajra (Pearl Millet)',
        'sowing_date' => '2026-04-01',
        'expected_harvest' => '2026-08-01',
        'actual_yield' => null,
        'investment' => 6000.00,
        'market_price' => 22.00,
        'image' => 'crop_1774356723_519.jpg',
        'predicted_yield' => 350.00,
        'quantity' => 80,
        'status' => 'growing',
        'acreage' => 4.50
    ],
    [
        'id' => 9,
        'farmer_id' => 5, // Ramesh
        'crop_name' => 'Bt Cotton',
        'sowing_date' => '2026-05-10',
        'expected_harvest' => '2026-10-10',
        'actual_yield' => null,
        'investment' => 18000.00,
        'market_price' => 75.00,
        'image' => 'crop_1774356753_952.jpg',
        'predicted_yield' => 500.00,
        'quantity' => 120,
        'status' => 'growing',
        'acreage' => 5.00
    ],
    [
        'id' => 10,
        'farmer_id' => 5, // Ramesh
        'crop_name' => 'Sweet Corn / Maize',
        'sowing_date' => '2026-03-15',
        'expected_harvest' => '2026-07-15',
        'actual_yield' => null,
        'investment' => 11000.00,
        'market_price' => 20.00,
        'image' => 'crop_1774534499_981.jpg',
        'predicted_yield' => 900.00,
        'quantity' => 180,
        'status' => 'growing',
        'acreage' => 7.00
    ],
    [
        'id' => 11,
        'farmer_id' => 2, // Ajay
        'crop_name' => 'White Cauliflower',
        'sowing_date' => '2026-04-05',
        'expected_harvest' => '2026-06-05',
        'actual_yield' => null,
        'investment' => 4000.00,
        'market_price' => 35.00,
        'image' => 'crop_1774534706_295.png',
        'predicted_yield' => 250.00,
        'quantity' => 150,
        'status' => 'for_sale',
        'acreage' => 2.00
    ],
    [
        'id' => 12,
        'farmer_id' => 4, // Suresh
        'crop_name' => 'Sweet Carrot',
        'sowing_date' => '2026-04-01',
        'expected_harvest' => '2026-06-15',
        'actual_yield' => null,
        'investment' => 3500.00,
        'market_price' => 40.00,
        'image' => 'crop_1774535354_208.png',
        'predicted_yield' => 300.00,
        'quantity' => 100,
        'status' => 'growing',
        'acreage' => 1.50
    ]
];

foreach ($crops as $c) {
    $stmt = $conn->prepare("INSERT INTO crops (id, farmer_id, crop_name, sowing_date, expected_harvest, actual_yield, investment, market_price, image, predicted_yield, quantity, status, acreage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param(
        "iisssdddsdisd",
        $c['id'],
        $c['farmer_id'],
        $c['crop_name'],
        $c['sowing_date'],
        $c['expected_harvest'],
        $c['actual_yield'],
        $c['investment'],
        $c['market_price'],
        $c['image'],
        $c['predicted_yield'],
        $c['quantity'],
        $c['status'],
        $c['acreage']
    );
    
    if ($stmt->execute()) {
        echo "Seeded crop: {$c['crop_name']} (Status: {$c['status']})\n";
    } else {
        echo "Failed to seed crop {$c['crop_name']}: " . $stmt->error . "\n";
    }
}


// --- 3. SEED ORDERS & ORDER ITEMS ---
$orders = [
    [
        'id' => 1,
        'buyer_id' => 3, // Raju
        'total_amount' => 480.00,
        'payment_method' => 'razorpay',
        'razorpay_order_id' => 'order_mock_001',
        'razorpay_payment_id' => 'pay_mock_001',
        'payment_status' => 'paid',
        'order_status' => 'delivered',
        'created_at' => date('Y-m-d H:i:s', strtotime('-11 days')),
        'delivery_name' => 'Buyer Raju',
        'delivery_address' => 'Shop 24, Main Sabzi Mandi, Azadpur',
        'delivery_city' => 'Delhi',
        'delivery_pin' => '110033',
        'delivery_phone' => '9876543211',
        'discount_applied' => 0.00,
        'promo_code' => '',
        'items' => [
            ['crop_id' => 2, 'quantity' => 10, 'price' => 30.50], // Tomato
            ['crop_id' => 11, 'quantity' => 5, 'price' => 35.00]  // Cauliflower
        ]
    ],
    [
        'id' => 2,
        'buyer_id' => 6, // Priya
        'total_amount' => 900.00,
        'payment_method' => 'cod',
        'razorpay_order_id' => null,
        'razorpay_payment_id' => null,
        'payment_status' => 'pending',
        'order_status' => 'processing',
        'created_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
        'delivery_name' => 'Priya Sharma (GreenBasket)',
        'delivery_address' => 'Warehouse A, MIDC Industrial Area, Andheri East',
        'delivery_city' => 'Mumbai',
        'delivery_pin' => '400069',
        'delivery_phone' => '9876543214',
        'discount_applied' => 0.00,
        'promo_code' => '',
        'items' => [
            ['crop_id' => 6, 'quantity' => 50, 'price' => 18.00]  // Potato
        ]
    ],
    [
        'id' => 3,
        'buyer_id' => 7, // Vikram
        'total_amount' => 610.00,
        'payment_method' => 'razorpay',
        'razorpay_order_id' => 'order_mock_002',
        'razorpay_payment_id' => 'pay_mock_002',
        'payment_status' => 'paid',
        'order_status' => 'shipped',
        'created_at' => date('Y-m-d H:i:s', strtotime('-1 days')),
        'delivery_name' => 'Vikram Hegde (The Organic Table)',
        'delivery_address' => '12, 100 Feet Road, Indiranagar',
        'delivery_city' => 'Bangalore',
        'delivery_pin' => '560038',
        'delivery_phone' => '9876543215',
        'discount_applied' => 0.00,
        'promo_code' => '',
        'items' => [
            ['crop_id' => 2, 'quantity' => 20, 'price' => 30.50]  // Tomato
        ]
    ]
];

foreach ($orders as $o) {
    $stmt = $conn->prepare("INSERT INTO orders (id, buyer_id, total_amount, payment_method, razorpay_order_id, razorpay_payment_id, payment_status, order_status, created_at, delivery_name, delivery_address, delivery_city, delivery_pin, delivery_phone, discount_applied, promo_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param(
        "isdsssssssssssdd",
        $o['id'],
        $o['buyer_id'],
        $o['total_amount'],
        $o['payment_method'],
        $o['razorpay_order_id'],
        $o['razorpay_payment_id'],
        $o['payment_status'],
        $o['order_status'],
        $o['created_at'],
        $o['delivery_name'],
        $o['delivery_address'],
        $o['delivery_city'],
        $o['delivery_pin'],
        $o['delivery_phone'],
        $o['discount_applied'],
        $o['promo_code']
    );
    
    if ($stmt->execute()) {
        echo "Seeded order: ID {$o['id']} for Buyer {$o['buyer_id']}\n";
        
        foreach ($o['items'] as $item) {
            $istmt = $conn->prepare("INSERT INTO order_items (order_id, crop_id, quantity, price) VALUES (?, ?, ?, ?)");
            $istmt->bind_param("iiid", $o['id'], $item['crop_id'], $item['quantity'], $item['price']);
            if ($istmt->execute()) {
                echo "  Added order item: Crop ID {$item['crop_id']}, Qty: {$item['quantity']}\n";
            } else {
                echo "  Failed to add order item: " . $istmt->error . "\n";
            }
        }
    } else {
        echo "Failed to seed order: " . $stmt->error . "\n";
    }
}


// --- 4. SEED REVIEWS ---
$reviews = [
    [
        'order_id' => 1,
        'farmer_id' => 2, // Ajay
        'buyer_id' => 3,  // Raju
        'rating' => 5,
        'comment' => 'Amazing fresh tomatoes and cauliflower! Delivered quickly and packed well. The quality is exceptional.'
    ],
    [
        'order_id' => 3,
        'farmer_id' => 2, // Ajay
        'buyer_id' => 7,  // Vikram
        'rating' => 4,
        'comment' => 'Hybrid Roma tomatoes are very firm and high-quality, perfect for our gourmet pasta sauces. Will definitely purchase again.'
    ]
];

foreach ($reviews as $r) {
    $stmt = $conn->prepare("INSERT INTO reviews (order_id, farmer_id, buyer_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiis", $r['order_id'], $r['farmer_id'], $r['buyer_id'], $r['rating'], $r['comment']);
    if ($stmt->execute()) {
        echo "Seeded review for Order {$r['order_id']}\n";
    } else {
        echo "Failed to seed review: " . $stmt->error . "\n";
    }
}


// --- 5. SEED ENQUIRIES & ENQUIRY MESSAGES ---
$enquiries = [
    [
        'id' => 1,
        'crop_id' => 1, // Wheat (Ajay)
        'custom_price' => 23.50,
        'custom_quantity' => 80,
        'offer_status' => 'pending',
        'buyer_id' => 6,  // Priya
        'farmer_id' => 2, // Ajay
        'messages' => [
            ['sender_id' => 6, 'message' => "Hello Ajay, I am interested in buying 80 quintals of Kalyan Sona Wheat. Can you offer a custom bulk price of 22 INR/kg?"],
            ['sender_id' => 2, 'message' => "Hello Priya, 22 is a bit too low given the high grain weight and quality this year. However, I can offer 23.50 INR/kg if we close the deal soon."],
            ['sender_id' => 6, 'message' => "Thank you for the response. I've updated the custom bid to 23.50 INR/kg in the system. Let me check with my finance manager to approve the purchase."]
        ]
    ],
    [
        'id' => 2,
        'crop_id' => 6, // Potato (Suresh)
        'custom_price' => 16.00,
        'custom_quantity' => 150,
        'offer_status' => 'accepted',
        'buyer_id' => 3,  // Raju
        'farmer_id' => 4, // Suresh
        'messages' => [
            ['sender_id' => 3, 'message' => "Hi Suresh, we are running a French fry promotion and need 150 kg Russet Potatoes. Can we do 15 INR/kg?"],
            ['sender_id' => 4, 'message' => "Hi Raju, how about 16 INR/kg? Russets have very high starch and low moisture, which is ideal for frying."],
            ['sender_id' => 3, 'message' => "That sounds fair. 16 INR/kg it is. Accept the offer and I will pay via UPI right away."]
        ]
    ]
];

foreach ($enquiries as $e) {
    $stmt = $conn->prepare("INSERT INTO enquiries (id, crop_id, custom_price, custom_quantity, offer_status, buyer_id, farmer_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiddsii", $e['id'], $e['crop_id'], $e['custom_price'], $e['custom_quantity'], $e['offer_status'], $e['buyer_id'], $e['farmer_id']);
    
    if ($stmt->execute()) {
        echo "Seeded enquiry ID {$e['id']} for Crop ID {$e['crop_id']}\n";
        
        foreach ($e['messages'] as $idx => $m) {
            $mstmt = $conn->prepare("INSERT INTO enquiry_messages (enquiry_id, sender_id, message, is_read, created_at) VALUES (?, ?, ?, ?, ?)");
            $is_read = ($idx < count($e['messages']) - 1) ? 1 : 0; // last message is unread for recipient
            $time_offset = '-' . (count($e['messages']) - $idx) . ' hours';
            $msg_time = date('Y-m-d H:i:s', strtotime($time_offset));
            
            $mstmt->bind_param("iisis", $e['id'], $m['sender_id'], $m['message'], $is_read, $msg_time);
            if ($mstmt->execute()) {
                echo "  Added message from User {$m['sender_id']}\n";
            } else {
                echo "  Failed to add message: " . $mstmt->error . "\n";
            }
        }
    } else {
        echo "Failed to seed enquiry: " . $stmt->error . "\n";
    }
}


// --- 6. SEED FARM TASKS ---
$farm_tasks = [
    // Wheat Kalyan Sona (Crop 1 - Ajay)
    [
        'farmer_id' => 2,
        'crop_id' => 1,
        'task_type' => 'Fertilizer',
        'task_description' => 'Apply baseline NPK dose (Urea, DAP, Potash) for tillering support.',
        'due_date' => date('Y-m-d H:i:s', strtotime('-15 days')),
        'status' => 'completed',
        'is_autopilot' => 1
    ],
    [
        'farmer_id' => 2,
        'crop_id' => 1,
        'task_type' => 'Watering',
        'task_description' => 'Heavy crown root initiation (CRI) stage irrigation. Ensure soil has uniform moisture.',
        'due_date' => date('Y-m-d H:i:s', strtotime('-2 days')),
        'status' => 'completed',
        'is_autopilot' => 1
    ],
    [
        'farmer_id' => 2,
        'crop_id' => 1,
        'task_type' => 'Fertilizer',
        'task_description' => 'Apply second Urea top-dressing at late jointing stage.',
        'due_date' => date('Y-m-d H:i:s', strtotime('+5 days')),
        'status' => 'pending',
        'is_autopilot' => 1
    ],
    [
        'farmer_id' => 2,
        'crop_id' => 1,
        'task_type' => 'Weeding',
        'task_description' => 'Manual field inspection for broad-leaf weeds and apply selective organic herbicide if needed.',
        'due_date' => date('Y-m-d H:i:s', strtotime('+12 days')),
        'status' => 'pending',
        'is_autopilot' => 0
    ],
    [
        'farmer_id' => 2,
        'crop_id' => 1,
        'task_type' => 'Harvesting',
        'task_description' => 'Final wheat grain harvesting and moisture content assessment.',
        'due_date' => date('Y-m-d H:i:s', strtotime('+25 days')),
        'status' => 'pending',
        'is_autopilot' => 1
    ],

    // Barley (Crop 5 - Suresh)
    [
        'farmer_id' => 4,
        'crop_id' => 5,
        'task_type' => 'Watering',
        'task_description' => 'Irrigate crop during active growth phase. Keep root zone moist.',
        'due_date' => date('Y-m-d H:i:s', strtotime('+3 days')),
        'status' => 'pending',
        'is_autopilot' => 1
    ],
    [
        'farmer_id' => 4,
        'crop_id' => 5,
        'task_type' => 'Weeding',
        'task_description' => 'Remove weeds around secondary ridges to prevent moisture competition.',
        'due_date' => date('Y-m-d H:i:s', strtotime('+10 days')),
        'status' => 'pending',
        'is_autopilot' => 0
    ]
];

foreach ($farm_tasks as $t) {
    $stmt = $conn->prepare("INSERT INTO farm_tasks (farmer_id, crop_id, task_type, task_description, due_date, status, is_autopilot) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissssi", $t['farmer_id'], $t['crop_id'], $t['task_type'], $t['task_description'], $t['due_date'], $t['status'], $t['is_autopilot']);
    if ($stmt->execute()) {
        echo "Seeded farm task: {$t['task_type']} for Crop {$t['crop_id']}\n";
    } else {
        echo "Failed to seed farm task: " . $stmt->error . "\n";
    }
}


// --- 7. SEED CROP CLAIMS ---
$claims = [
    [
        'farmer_id' => 2, // Ajay
        'incident_date' => '2026-04-15',
        'cause' => 'Hailstorm Damage',
        'loss_percent' => 40,
        'farm_address' => 'Village Phillaur, Tehsil Phillaur, Jalandhar, Punjab',
        'total_acreage' => 2.50,
        'estimated_loss_inr' => 45000,
        'proof_image' => 'damage_1772770421_415.jpg',
        'proof_land_doc' => 'land_1772770421_856.jpg',
        'bank_account' => '123456789012',
        'ifsc_code' => 'SBIN0001234',
        'status' => 'Pending Review',
        'admin_remarks' => null
    ],
    [
        'farmer_id' => 4, // Suresh
        'incident_date' => '2026-03-10',
        'cause' => 'Severe Pest Infestation',
        'loss_percent' => 25,
        'farm_address' => 'Village Nissing, District Karnal, Haryana',
        'total_acreage' => 3.00,
        'estimated_loss_inr' => 20000,
        'proof_image' => 'claim_1772191880_8879.jpg',
        'proof_land_doc' => 'land_1772770421_856.jpg',
        'bank_account' => '987654321098',
        'ifsc_code' => 'HDFC0004321',
        'status' => 'Approved',
        'admin_remarks' => 'Verified with regional remote-sensing data and verified by agriculture extension officer. Disbursal initiated.'
    ]
];

foreach ($claims as $c) {
    $stmt = $conn->prepare("INSERT INTO crop_claims (farmer_id, incident_date, cause, loss_percent, farm_address, total_acreage, estimated_loss_inr, proof_image, proof_land_doc, bank_account, ifsc_code, status, admin_remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issisdidsssss", $c['farmer_id'], $c['incident_date'], $c['cause'], $c['loss_percent'], $c['farm_address'], $c['total_acreage'], $c['estimated_loss_inr'], $c['proof_image'], $c['proof_land_doc'], $c['bank_account'], $c['ifsc_code'], $c['status'], $c['admin_remarks']);
    if ($stmt->execute()) {
        echo "Seeded crop claim for Farmer {$c['farmer_id']} ({$c['cause']})\n";
    } else {
        echo "Failed to seed crop claim: " . $stmt->error . "\n";
    }
}


// --- 8. SEED NOTIFICATIONS ---
$notifications = [
    [
        'user_id' => 2, // Ajay
        'message' => '👋 Welcome to Farmogram! Set up your crop timelines to generate autopilot tasks.',
        'is_read' => 1
    ],
    [
        'user_id' => 2, // Ajay
        'message' => '🔔 Reminder: Scheduled Fertilizer top-dressing due in 5 days for your Wheat crop.',
        'is_read' => 0
    ],
    [
        'user_id' => 2, // Ajay
        'message' => '📬 Custom offer from Priya (GreenBasket) received for Wheat.',
        'is_read' => 0
    ],
    [
        'user_id' => 4, // Suresh
        'message' => '🎉 Good news! Your crop claim for Pest Infestation has been Approved. Disbursal of INR 20000 has been sent to your bank account.',
        'is_read' => 0
    ],
    [
        'user_id' => 3, // Raju
        'message' => '🚚 Your Order #1 has been shipped by Farmer Ajay and is out for delivery.',
        'is_read' => 1
    ],
    [
        'user_id' => 3, // Raju
        'message' => '⭐ Rate your recent purchase! Order #1 has been delivered. Please leave feedback for Farmer Ajay.',
        'is_read' => 0
    ]
];

foreach ($notifications as $n) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $n['user_id'], $n['message'], $n['is_read']);
    if ($stmt->execute()) {
        echo "Seeded notification for User {$n['user_id']}\n";
    } else {
        echo "Failed to seed notification: " . $stmt->error . "\n";
    }
}

echo "Database seeding finished successfully!\n";
