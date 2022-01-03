<?php
namespace Classes;
/*
 * Created by stikkx
 *
 *
 */
use Classes\Helper;

class EmailCsvBridge
{

    protected $mail_username;
    protected $mail_password;
    protected $mail_server;

    protected $source_mail_from;
    protected $dest_mail;

    protected $rename = [];

    public function __construct($mail_username,$mail_password, $mail_server, $source_mail_from, $dest_mail)
    {
        $this->mail_username = $mail_username;
        $this->mail_password = $mail_password;
        $this->mail_server = $mail_server;

        $this->source_mail_from = $source_mail_from;
        $this->dest_mail = $dest_mail;

    }

    public function getSourceCSV($move_mail = false)
    {

        $path_array = [];
        $inbox = imap_open($this->mail_server,$this->mail_username,$this->mail_password) or die('Cannot connect to MailServer: ' . imap_last_error());

        $emails = imap_search($inbox, 'FROM "'.$this->source_mail_from.'"');

        if($emails) {

            $count = 1;
            rsort($emails);
            foreach($emails as $email_number)
            {
                if($move_mail !== false){
                    imap_mail_move($inbox,$email_number,$move_mail);
                }
                $overview = imap_fetch_overview($inbox,$email_number,0);
                $message = imap_fetchbody($inbox,$email_number,2);
                $structure = imap_fetchstructure($inbox, $email_number);

                $attachments = array();

                /* if any attachments found... */
                if(isset($structure->parts) && count($structure->parts))
                {
                    for($i = 0; $i < count($structure->parts); $i++)
                    {
                        $attachments[$i] = array(
                            'is_attachment' => false,
                            'filename' => '',
                            'name' => '',
                            'attachment' => ''
                        );

                        if($structure->parts[$i]->ifdparameters)
                        {
                            foreach($structure->parts[$i]->dparameters as $object)
                            {
                                if(strtolower($object->attribute) == 'filename')
                                {
                                    $attachments[$i]['is_attachment'] = true;
                                    $attachments[$i]['filename'] = $object->value;
                                }
                            }
                        }

                        if($structure->parts[$i]->ifparameters)
                        {
                            foreach($structure->parts[$i]->parameters as $object)
                            {
                                if(strtolower($object->attribute) == 'name')
                                {
                                    $attachments[$i]['is_attachment'] = true;
                                    $attachments[$i]['name'] = $object->value;
                                }
                            }
                        }

                        if($attachments[$i]['is_attachment'])
                        {
                            $attachments[$i]['attachment'] = imap_fetchbody($inbox, $email_number, $i+1);

                            /* 3 = BASE64 encoding */
                            if($structure->parts[$i]->encoding == 3)
                            {
                                $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
                            }
                            /* 4 = QUOTED-PRINTABLE encoding */
                            elseif($structure->parts[$i]->encoding == 4)
                            {
                                $attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
                            }
                        }
                    }
                }

                /* iterate through each attachment and save it */
                $path_array = [];
                foreach($attachments as $attachment)
                {
                    if($attachment['is_attachment'] == 1)
                    {
                        $filename = $attachment['name'];
                        if(empty($filename)) $filename = $attachment['filename'];

                        if(empty($filename) || !strpos($filename,"csv")) continue;
                        $folder = "attachment";
                        if(!is_dir($folder))
                        {
                            mkdir($folder);
                        }
                        $path = "./". $folder ."/". $email_number . "-" . $filename;
                        $fp = fopen("./". $folder ."/". $email_number . "-" . $filename, "w+");
                        array_push($path_array,$path);
                        fwrite($fp, $attachment['attachment']);
                        fclose($fp);
                    }
                }
            }
        }

        /* close the connection */
        imap_close($inbox);
        return $path_array;

    }

    public function editCSVHeader($csv_path,$rename)
    {
        $this->rename = $rename;
        $csv = array_map('str_getcsv', file($csv_path));
        foreach($csv[0] as $col => $colname) {
            if(!empty($rename[$colname])) $csv[0][$col] = $rename[$colname];
        }
        array_walk($csv, function(&$a) use ($csv) {
            $a = array_combine($csv[0], $a); });
        $data = $csv;
        //Helper::printr($data);
        return $data;
    }

    public function saveToCSV($csv_path, $data)
    {
        $fp = fopen($csv_path, 'w');

        foreach ($data as $fields) {
            fputcsv($fp, $fields);
        }

        fclose($fp);
    }

    public function sendEmail($csv_path)
    {

        $htmlContent = ' <h3>New CSV!</h3>';
        $headers = "From: EmailCsvBridge"." <".$this->mail_username.">";

        $semi_rand = md5(time());
        $mime_boundary = "==Multipart_Boundary_x{$semi_rand}x";
        $headers .= "\nMIME-Version: 1.0\n" . "Content-Type: multipart/mixed;\n" . " boundary=\"{$mime_boundary}\"";
        $message = "--{$mime_boundary}\n" . "Content-Type: text/html; charset=\"UTF-8\"\n" .
            "Content-Transfer-Encoding: 7bit\n\n" . $htmlContent . "\n\n";

        if(!empty($csv_path) > 0){
            if(is_file($csv_path)){
                $message .= "--{$mime_boundary}\n";
                $fp =    @fopen($csv_path,"rb");
                $data =  @fread($fp,filesize($csv_path));

                @fclose($fp);
                $data = chunk_split(base64_encode($data));
                $message .= "Content-Type: application/octet-stream; name=\"".basename($csv_path)."\"\n" .
                    "Content-Description: ".basename($csv_path)."\n" .
                    "Content-Disposition: attachment;\n" . " filename=\"".basename($csv_path)."\"; size=".filesize($csv_path).";\n" .
                    "Content-Transfer-Encoding: base64\n\n" . $data . "\n\n";
            }
        }
        $message .= "--{$mime_boundary}--";
        $returnpath = "-f" . $this->mail_username;

        $mail = @mail($this->dest_mail, "New Mail From EmailCSVBridge", $message, $headers, $returnpath);
        echo $mail?"<h1>Email Sent Successfully!</h1>":"<h1>Email sending failed.</h1>";

    }


}


