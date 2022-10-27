<?php

    class Imap {

        protected $host;
        protected $port;
        protected $user;
        protected $pass;
        protected $ssl;
        protected $novalidate;
        protected $box;
        protected $connected;


        /**
          * Construct method
          *
          * @return void
          */

          public function __construct() {

            if (!extension_loaded('imap')) {
                throw new Exception('Missing extension');
            }
        }


        /**
          * Set connection params
          *
          * @param string $host
          * @param int $port (optional)
          * @param string $ssl (optional)
          * @param string $novalidate (optional)
          *
          * @return bool
          */

        public function host(string $host, int $port = 25, string $ssl = null, string $novalidate = null): bool {

            $this->host       = $host;
            $this->port       = $port;
            $this->ssl        = $ssl;
            $this->novalidate = $novalidate;

            return true;
        }


        /**
          * Set authentication params
          *
          * @param string $user
          * @param string $pass
          *
          * @return bool
          */

        public function auth(string $user, string $pass): bool {

            $this->user = $user;
            $this->pass = $pass;

            return true;
        }


        /**
          * Connect to IMAP
          *
          * @return bool
          */

        public function connect(): bool {

            $connection = '{' . $this->host . ':' . $this->port . '/imap' . ($this->ssl ? '/ssl' : '') . ($this->novalidate ? '/novalidate-cert' : '') . '}INBOX';
            $this->box  = imap_open($connection, $this->user, $this->pass);

            if ($this->box) {
                $this->connected = true;
                return true;
            }

            return false;
        }


        /**
          * Return total email
          *
          * @return int
          */

        public function total(): int {

            return imap_num_msg($this->box);
        }


        /**
          * Return total email unread
          *
          * @return int
          */

        public function unread(): int {

            return imap_search($this->box, 'UNSEEN');
        }


        /**
          * Return email by $id
          *
          * @param int $id
          *
          * @return array
          */

        public function email(int $id): array {

            if (!$this->connected) {
                return false;
            }

            $data         = $this->header($id);
            $data['body'] = $this->body($id);

            if ($attachments = $this->attachment($id)) {
                $data['attachments'] = $attachments;
            }

            return $data;
        }


        /**
          * Return header email
          *
          * @param int $id
          *
          * @return array
          */

        public function header(int $id): array {

            if (!$this->connected) {
                return false;
            }

            $header = @imap_headerinfo($this->box, $id);

            if (!$header) {
                return false;
            }

            $sender  = $header->from[0];
            $details = [];

            if (strtolower($sender->mailbox) == 'mailer-daemon' || strtolower($sender->mailbox) == 'postmaster') {
                return false;
            }

            $details = [
                'date'    => date('Y-m-d', $header->udate),
                'time'    => date('H:i:s', $header->udate),
                'from'    => strtolower($sender->mailbox) . '@' . $sender->host,
                'name'    => isset($sender->personal) ? imap_utf8($sender->personal) : null,
                'subject' => iconv_mime_decode($header->subject, 0, 'utf-8'),
            ];

            if (isset($header->reply_to[0])) {
                $reply_to = $header->reply_to[0];
                $details['reply_to']      = strtolower($reply_to->mailbox) . '@' . $reply_to->host;
                $details['reply_to_name'] = isset($reply_to->personal) ? imap_utf8($reply_to->personal) : null;
            }

            if (isset($header->toaddress)) {
                $details['to'] = $header->toaddress;
            }

            return $details;
        }


        /**
          * Return body email
          *
          * @param int $id
          * @param string $format (optional)
          *
          * @return string
          */

        public function body(int $id, string $format = 'html'): string {

            if (!$this->connected) {
                return false;
            }

            $body = '';

            if (strtolower($format) == 'html') {
                $body = $this->get_part($this->box, $id, 'TEXT/HTML');
            }

            if ($body == '') {
                $body = $this->get_part($this->box, $id, 'TEXT/PLAIN');
            }

            if ($body == '') {
                return '';
            }

            return $body;
        }


        /**
          * Delete email
          *
          * @param int $id
          *
          * @return bool
          */

        public function delete(int $id): bool {

            if (!$this->connected) {
                return false;
            }

            return imap_delete($this->box, $id);
        }


        /**
          * Mark email as read
          *
          * @param int $id
          *
          * @return bool
          */

        public function mark_as_read(int $id): bool {

            if (!$this->connected) {
                return false;
            }

            return imap_setflag_full($this->box, $id, '\Seen');
        }


        /**
          * Get or download attachments
          *
          * @param int $id
          * @param string $folder (optional)
          *
          * @return mixed
          */

        public function attachment(int $id, string $folder = null): mixed {

            $attachments = [];
            $structure   = imap_fetchstructure($this->box, $id);

            if (isset($structure->parts) && count($structure->parts)) {

                for($i = 0; $i < count($structure->parts); $i++) {
                    if ($structure->parts[$i]->ifdparameters) {
                        foreach ($structure->parts[$i]->dparameters as $object) {
                            if (strtolower($object->attribute) == 'filename') {
                                $attachments[$i]['filename'] = $object->value;
                            }
                        }
                    }

                    if ($structure->parts[$i]->ifparameters) {
                        foreach ($structure->parts[$i]->parameters as $object) {
                            if (strtolower($object->attribute) == 'name') {
                                $attachments[$i]['name'] = $object->value;
                            }
                        }
                    }

                    if (isset($attachments[$i])) {
                        $attachments[$i]['attachment'] = imap_fetchbody($this->box, $id, $i + 1);
                        switch ($structure->parts[$i]->encoding) {
                            case 0: $attachments[$i]['attachment'] = imap_8bit($attachments[$i]['attachment']); break;
                            case 1: $attachments[$i]['attachment'] = imap_8bit($attachments[$i]['attachment']); break;
                            case 2: $attachments[$i]['attachment'] = imap_binary($attachments[$i]['attachment']); break;
                            case 3: $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']); break;
                            case 4: $attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']); break;
                            case 5: $attachments[$i]['attachment'] = $attachments[$i]['attachment']; break;
                        }
                    }
                }
            }

            $attachments = array_values($attachments);

            if (!$attachments) {
                return true;
            }

            if ($folder) {
                $folder = trim($folder, '/') . '/';

                if (!is_dir($folder)) {
                    mkdir($folder, 0755, true);
                }
            }

            $results = [];
            $success = true;
            foreach ($attachments as $attachment) {
                $name      = $attachment['name'];
                $contents  = $attachment['attachment'];
                $results[] = $name;

                if ($folder) {
                    file_put_contents($folder . $name, $contents);
                    if (!is_file($folder . $name)) {
                        $success = false;
                    }
                }
            }

            if ($folder) {
                return $success;
            }

            return $results;
        }


        /**
          * Get Mime Type
          *
          * @param mixed $structure
          *
          * @return string
          */

        protected function get_mime_type(mixed &$structure): string {

            $primary_mime_type = ['TEXT', 'MULTIPART', 'MESSAGE', 'APPLICATION', 'AUDIO', 'IMAGE', 'VIDEO', 'OTHER'];

            if ($structure->subtype) {
                return $primary_mime_type[(int)$structure->type] . '/' . $structure->subtype;
            }

            return 'TEXT/PLAIN';
        }


        /**
          * Get Part
          *
          * @param mixed $stream
          * @param int $id
          * @param string $mime_type
          * @param mixed $structure (optional)
          * @param string $part_number (optional)
          *
          * @return string
          */

        protected function get_part(mixed $stream, int $id, string $mime_type, mixed $structure = null, string $part_number = null): string {

            if (!$structure) {
                $structure = imap_fetchstructure($stream, $id);
            }

            if ($structure) {
                if ($mime_type == $this->get_mime_type($structure)) {
                    if (!$part_number) {
                        $part_number = '1';
                    }

                    $text = imap_fetchbody($stream, $id, $part_number);

                    switch ($structure->encoding) {
                        case 1: return imap_utf8($text); break;
                        case 3: return imap_base64($text); break;
                        case 4: return imap_qprint($text); break;
                        default: return $text; break;
                    }
                }

                if ($structure->type == 1) {
                    foreach ($structure->parts as $index => $sub_structure) {
                        $prefix = null;
                        if ($part_number) {
                            $prefix = $part_number . '.';
                        }

                        $data = $this->get_part($stream, $id, $mime_type, $sub_structure, $prefix . ($index + 1));

                        if ($data) {
                            return $data;
                        }
                    }
                }
            }

            return false;
        }


        /**
          * Close connection
          *
          * @return bool
          */

        public function __destruct() {

            if (!$this->connected) {
                return false;
            }

            imap_expunge($this->box);

            return imap_close($this->box, CL_EXPUNGE);
        }
    }
