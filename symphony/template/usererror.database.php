<?php

use \SymphonyCms\Toolkit\HTMLPage;

$Page = new HTMLPage();

$Page->Html->setElementStyle('html');

$Page->Html->setDTD('<!DOCTYPE html>');
$Page->Html->setAttribute('xml:lang', 'en');
$Page->addElementToHead(new XMLElement('meta', null, array('http-equiv' => 'Content-Type', 'content' => 'text/html; charset=UTF-8')), 0);
$Page->addStylesheetToHead(APP_URL . '/assets/css/symphony.css', 'screen', 30);
$Page->addStylesheetToHead(APP_URL . '/assets/css/symphony.frames.css', 'screen', 31);

$Page->setHttpStatus($e->getHttpStatusCode());
$Page->addHeaderToPage('Content-Type', 'text/html; charset=UTF-8');
$Page->addHeaderToPage('Symphony-Error-Type', 'database');

if (isset($e->getAdditional()->header)) {
    $Page->addHeaderToPage($e->getAdditional()->header);
}

$Page->setTitle(tr('%1$s &ndash; %2$s', array(tr('Symphony'), tr('Database Error'))));
$Page->Body->setAttribute('id', 'error');

$div = new XMLElement('div', null, array('class' => 'frame'));
$div->appendChild(new XMLElement('h1', tr('Symphony Database Error')));
$div->appendChild(new XMLElement('p', $e->getAdditional()->message));
$div->appendChild(new XMLElement('p', '<code>'.$e->getAdditional()->error->getDatabaseErrorCode().': '.$e->getAdditional()->error->getDatabaseErrorMessage().'</code>'));

$query = $e->getAdditional()->error->getQuery();

if (isset($query)) {
    $div->appendChild(new XMLElement('p', '<code>'.$e->getAdditional()->error->getQuery().'</code>'));
}

$Page->Body->appendChild($div);

$output = $Page->generate();

echo $output;

exit;
