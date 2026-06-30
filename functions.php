<?php
// Centralized functions for the application

// Define file paths relative to the project root (parent of this 'includes' directory)
define('DATA_FILE', __DIR__ . '/data.json');
define('CATEGORIES_FILE', __DIR__ . '/categories.json');

// Function to load data from JSON file
function getData()
{
    if (!file_exists(DATA_FILE)) {
        error_log("DATA_FILE not found: " . DATA_FILE);
        return ['incomes' => [], 'expenses' => [], 'items' => [], 'rents' => []];
    }
    $jsonData = @file_get_contents(DATA_FILE);
    if ($jsonData === false) {
        error_log("Failed to read DATA_FILE: " . DATA_FILE . " - " . error_get_last()['message']);
        $_SESSION['message'] = 'ไม่สามารถอ่านไฟล์ข้อมูลหลักได้ กรุณาลองใหม่ภายหลัง';
        return ['incomes' => [], 'expenses' => [], 'items' => [], 'rents' => []];
    }
    $data = json_decode($jsonData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error in DATA_FILE: " . json_last_error_msg());
        $_SESSION['message'] = 'ไฟล์ข้อมูลหลักเสียหาย กรุณาติดต่อผู้ดูแลระบบ';
        return ['incomes' => [], 'expenses' => [], 'items' => [], 'rents' => []];
    }
    $data = is_array($data) ? $data : [];
    $data += ['incomes' => [], 'expenses' => [], 'items' => [], 'rents' => []];
    return $data;
}

// Function to save data to JSON file
function saveData($data): bool
{
    $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($jsonData === false) {
        error_log("JSON encode error for DATA_FILE: " . json_last_error_msg());
        $_SESSION['message'] = 'ไม่สามารถบันทึกข้อมูลได้: ข้อมูลไม่ถูกต้อง';
        return false;
    }
    if (@file_put_contents(DATA_FILE, $jsonData) === false) {
        error_log("Failed to write DATA_FILE: " . DATA_FILE . " - " . error_get_last()['message']);
        $_SESSION['message'] = 'ไม่สามารถบันทึกไฟล์ข้อมูลหลักได้ กรุณาลองใหม่ภายหลัง';
        return false;
    }
    return true;
}

// Function to load categories and settings from JSON file
function getCategories()
{
    $defaultCategories = [
        'income_categories' => ['เงินค่าประกัน', 'เงินค่าห้อง', 'ขายของ', 'ดอกเบี้ย/ปันผล', 'เงินให้เปล่า', 'เงินยืม', 'อื่นๆ'],
        'expense_categories' => ['วัสดุและอุปกรณ์', 'ค่าไฟ', 'ค่าน้ำ', 'บิลและสาธารณูปโภค', 'สุขภาพ', 'ค่าอาหาร', 'อื่นๆ'],
        'utility_prices' => ['water_per_unit' => 18, 'electricity_per_unit' => 8, 'min_water_charge' => 100]
    ];
    if (!file_exists(CATEGORIES_FILE)) {
        error_log("CATEGORIES_FILE not found, creating default: " . CATEGORIES_FILE);
        file_put_contents(CATEGORIES_FILE, json_encode($defaultCategories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $defaultCategories;
    }
    $json = @file_get_contents(CATEGORIES_FILE);
    if ($json === false) {
        error_log("Failed to read CATEGORIES_FILE: " . CATEGORIES_FILE . " - " . error_get_last()['message']);
        $_SESSION['message'] = 'ไม่สามารถอ่านไฟล์หมวดหมู่ได้ กรุณาลองใหม่ภายหลัง';
        return $defaultCategories;
    }
    $categories = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error in CATEGORIES_FILE: " . json_last_error_msg());
        $_SESSION['message'] = 'ไฟล์หมวดหมู่เสียหาย กรุณาติดต่อผู้ดูแลระบบ';
        return $defaultCategories;
    }
    $categories = is_array($categories) ? $categories : $defaultCategories;
    // Merge default values for any missing keys to avoid errors on older installations
    $categories = array_replace_recursive($defaultCategories, $categories);
    return $categories;
}