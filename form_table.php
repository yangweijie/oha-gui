
<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use Kingbes\Libui\App;
use Kingbes\Libui\Window;
use Kingbes\Libui\Control;
use Kingbes\Libui\Table;
use Kingbes\Libui\TableValueType;
use Kingbes\Libui\Label;
use Kingbes\Libui\Entry;
use Kingbes\Libui\Button;
use Kingbes\Libui\Separator;
use Kingbes\Libui\Box;

App::init();
$window = Window::create('Contacts', 600, 600, 0);
Window::setMargined($window, true);
Window::onClosing($window, function ($window) {
    App::quit();
    return 1;
});

$contactsOrigin = [
    ['Lisa Sky', 'lisa@sky.com', '720-523-4329', 'Denver', 'CO'],
    ['Jordan Biggins', 'jordan@biggins.', '617-528-5399', 'Boston', 'MA'],
    ['Mary Glass', 'mary@glass.con', '847-589-8788', 'Elk Grove Village', 'IL'],
    ['Darren McGrath', 'darren@mcgrat', '206-539-9283', 'Seattle', 'WA'],
    ['Melody Hanheir', 'melody@hanhei', '213-493-8274', 'Los Angeles', 'CA'],
];
$contacts = $contactsOrigin;
$filteredContacts = $contactsOrigin;

$fields = ['Name', 'Email', 'Phone', 'City', 'State'];
$entries = [];
$formBox = Box::newVerticalBox();
Box::setPadded($formBox, true);
foreach ($fields as $field) {
    $label = Label::create($field);
    $entry = Entry::create();
    Box::append($formBox, $label, false);
    Box::append($formBox, $entry, false);
    $entries[$field] = $entry;
}
$saveBtn = Button::create('Save Contact');
Box::append($formBox, $saveBtn, false);
Box::append($formBox, Separator::createHorizontal(), false);

$searchEntry = Entry::create();
Entry::setText($searchEntry, '');
$searchBtn = Button::create('Search');
$searchBox = Box::newHorizontalBox();
Box::setPadded($searchBox, true);
Box::append($searchBox, $searchEntry, true); // 输入框可伸缩
Box::append($searchBox, $searchBtn, false);
Box::append($formBox, $searchBox, false);
Box::append($formBox, Separator::createHorizontal(), false);

// 表格模型处理器
$getTableModelHandler = function() use (&$filteredContacts) {
    return Table::modelHandler(
        5,
        TableValueType::String,
        count($filteredContacts),
        function ($handler, $row, $column) use (&$filteredContacts) {
            return Table::createValueStr($filteredContacts[$row][$column]);
        },
        function ($handler, $row, $column, $v) use (&$filteredContacts) {
            // 可扩展编辑功能
        }
    );
};

$tableModel = Table::createModel($getTableModelHandler());
$table = Table::create($tableModel, -1);
Table::appendTextColumn($table, 'Name', 0, false);
Table::appendTextColumn($table, 'Email', 1, false);
Table::appendTextColumn($table, 'Phone', 2, false);
Table::appendTextColumn($table, 'City', 3, false);
Table::appendTextColumn($table, 'State', 4, false);
Box::append($formBox, $table, true);

// 保存联系人事件
Button::onClicked($saveBtn, function () use (&$contacts, &$filteredContacts, $entries, $window, $table, $getTableModelHandler, $tableModel) {
    $row = [];
    $allFilled = true;
    foreach (['Name', 'Email', 'Phone', 'City', 'State'] as $field) {
        $val = Entry::text($entries[$field]);
        if (trim($val) === '') {
            $allFilled = false;
        }
        $row[] = $val;
    }
    if ($allFilled) {
        foreach ($entries as $entry) {
            Entry::setText($entry, '');
        }
        $contactsOrigin[] = $row;
        $contacts = $contactsOrigin;
        $filteredContacts = $contactsOrigin;
        Table::modelRowInserted($tableModel, count($filteredContacts)-1);
    }

});

// 搜索过滤事件
Button::onClicked($searchBtn, function () use (&$contacts, &$filteredContacts, $searchEntry, $table, $getTableModelHandler, $tableModel) {
    global $contactsOrigin;
    $keyword = trim(Entry::text($searchEntry));
    $filteredContacts = [];
    if ($keyword === '') {
        $filteredContacts = $contactsOrigin;
    } else {
        foreach ($contactsOrigin as $row) {
            $found = false;
            foreach ($row as $cell) {
                if (strpos($cell, $keyword) !== false) {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                $filteredContacts[] = $row;
            }
        }
    }
    // 清空并重建表格内容
    for ($i=0; $i < count($contacts); $i++) { 
        Table::modelRowDeleted($tableModel, count($contacts) -($i+1));
    }
    $contacts = $filteredContacts;
    foreach ($filteredContacts as $i => $row) {
        Table::modelRowInserted($tableModel, $i);
    }
});

Window::setChild($window, $formBox);
Control::show($window);
App::main();
