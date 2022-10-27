# PHP IMAP Class

Standalone class in pure PHP that allows you to read e-mails via the IMAP protocol. It does not need a dependency, just import it and use its methods, described below.

``` php

include 'imap.class.php';

$imap = new Imap();

// set host, port, ssl and novalidate 
$imap->host('imap.example.com', 993, true, false);

// set username and password
$imap->auth('user@example.com', 'password');

// connect to server
$imap->connect();

// get total emails
$imap->total();

// get total emails unread
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

```

:star: **If you liked what I did, if it was useful to you or if it served as a starting point for something more magical let me know with a star** :green_heart:
