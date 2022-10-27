<?php

include 'imap.class.php';

$imap = new Imap();

// set host, port and encryption
$imap->host('imap.example.com', 993, true);

// set username and password
$imap->auth('user@example.com', 'password');

// connect to server
$imap->connect();

// get total emails
$imap->total();

// get total email unread
$imap->unread();

// get single email by index
$imap->email(81);

// get attachments of single email by index
$imap->attachment(81);

// get header of single email by index
$imap->header(81);

// get body of single email by index
$imap->body(81);

// set email read by index
$imap->mark_as_read(81);

// delete email by index
$imap->delete(81);