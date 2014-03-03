
<?php

session_start();

function pg_connection_string_from_database_url() {
  extract(parse_url($_ENV["DATABASE_URL"]));
  return "user=$user password=$pass host=$host dbname=" . substr($path, 1);
}

if (isset($_POST['data'])) {

   $str_json = $_POST['data'];
   $str_decode = json_decode($str_json, true);
   $str = $str_decode["ID"];

   //$str_1 = $str["hello"];
   //echo json_encode($str_1);

   // Initialize net poss, negs, objs variables
   $poss_wrd_net = 0;
   $negs_wrd_net = 0;
   $objs_wrd_net = 0;
   $wrds_tot = 0;

   foreach ($str as $i => $value) {

     // Input string
     $wrd = $i; //'able';
     $pos_wrd = $str[$i]; //'a';
     //if ($pos_wrd == 'n') $pos_wrd = 'i';

     if ($str[$i] != "i") {

       $wrds_tot++;

       // Fetch data from database (postgresql)
       $connect = pg_connect(pg_connection_string_from_database_url());

       //echo json_encode($connect);
       $result = pg_exec($connect, "SELECT * FROM test_table1 WHERE synset='$wrd'");
       $numrows = pg_numrows($result);

       if ($numrows > 0) {
         // Initialize local variables
         $poss_wrd = 0;
         $negs_wrd = 0;
         $objs_wrd = 0;
         $poss_wrd_loc = 0;
         $negs_wrd_loc = 0;
         $objs_wrd_loc = 0;

         // Loop through all resulted rows and find the appropriate pos
         for ($ri = 0; $ri < $numrows; $ri++) {
           $row = pg_fetch_array($result, $ri);
           if ($row["pos"] == $pos_wrd) {
     	      $poss_wrd = $row["poss"];
     	      $negs_wrd = $row["negs"];
     	      $objs_wrd = $row["objs"];
	      break;
           } else {
             $poss_wrd_loc += $row["poss"];
             $negs_wrd_loc += $row["negs"];
             $objs_wrd_loc += $row["objs"];
           }
         }

         if ( ($poss_wrd == 0) && ($negs_wrd == 0) && ($objs_wrd == 0) ) {
           if ($numrows == 0) {
             // If word is not identified, it is treated as objective
             $poss_wrd = 0;
             $negs_wrd = 0;
             $objs_wrd = 1;
           } else {
             // If parts of speech is not identified, it is treated as
             // average of all available in db
             $poss_wrd = $poss_wrd_loc / $numrows;
             $negs_wrd = $negs_wrd_loc / $numrows;
             $objs_wrd = $objs_wrd_loc / $numrows;
           }
         } else {
           //$poss_wrd = $poss_wrd_loc / $numrows;
           //$negs_wrd = $negs_wrd_loc / $numrows;
           //$objs_wrd = $objs_wrd_loc / $numrows;
         }

         // Update net poss, negs, objs
         $poss_wrd_net += $poss_wrd;
         $negs_wrd_net += $negs_wrd;
         $objs_wrd_net += $objs_wrd;

       } // end if ($numrows > 0)
       else {
         $wrds_tot--;
         continue;
       }

     } // end if $str[$i]!="i"

   }

   if ($wrds_tot > 0) {
     $poss_wrd_net /= $wrds_tot;
     $negs_wrd_net /= $wrds_tot;
     $objs_wrd_net /= $wrds_tot;
   } else {
     $poss_wrd_net = 0;
     $negs_wrd_net = 0;
     $objs_wrd_net = 1;
   }

   // Create a php array and send it
   $msg_score = array(
		     "poss" => $poss_wrd_net,
		     "negs" => $negs_wrd_net,
		     "objs" => $objs_wrd_net
		     );

   //$numrows = multiply_by_two($numrows);
   $numrows = "$numrows";
   
   pg_close($connect);
   // End of database query

   echo json_encode($msg_score);
   ////echo json_encode($numrows);

}

?>
