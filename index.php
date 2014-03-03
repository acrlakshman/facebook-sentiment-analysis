<?php

/**
 * This sample app is provided to kickstart your experience using Facebook's
 * resources for developers.  This sample app provides examples of several
 * key concepts, including authentication, the Graph API, and FQL (Facebook
 * Query Language). Please visit the docs at 'developers.facebook.com/docs'
 * to learn more about the resources available to you
 */

// Provides access to app specific values such as your app id and app secret.
// Defined in 'AppInfo.php'
require_once('AppInfo.php');

// Enforce https on production
if (substr(AppInfo::getUrl(), 0, 8) != 'https://' && $_SERVER['REMOTE_ADDR'] != '127.0.0.1') {
  header('Location: https://'. $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
  exit();
}

// This provides access to helper functions defined in 'utils.php'
require_once('utils.php');


/*****************************************************************************
 *
 * The content below provides examples of how to fetch Facebook data using the
 * Graph API and FQL.  It uses the helper functions defined in 'utils.php' to
 * do so.  You should change this section so that it prepares all of the
 * information that you want to display to the user.
 *
 ****************************************************************************/

require_once('sdk/src/facebook.php');

$facebook = new Facebook(array(
  'appId'  => AppInfo::appID(),
  'secret' => AppInfo::appSecret(),
  'sharedSession' => true,
  'trustForwarded' => true,
));

$user_id = $facebook->getUser();

if ($user_id) {
  try {
    // Fetch the viewer's basic information
    $basic = $facebook->api('/me');
  } catch (FacebookApiException $e) {
    // If the call fails we check if we still have a user. The user will be
    // cleared if the error is because of an invalid accesstoken
    if (!$facebook->getUser()) {
      header('Location: '. AppInfo::getUrl($_SERVER['REQUEST_URI']));
      exit();
    }
  }

  // This fetches some things that you like . 'limit=*" only returns * values.
  // To see the format of the data you are retrieving, use the "Graph API
  // Explorer" which is at https://developers.facebook.com/tools/explorer/
  $likes = idx($facebook->api('/me/likes?limit=4'), 'data', array());

  // This fetches all of your friends.
  //$friends = idx($facebook->api('/me/friends?limit=4'), 'data', array());
  $friends = idx($facebook->api('/me/friends'), 'data', array());

  // And this returns 16 of your photos.
  $photos = idx($facebook->api('/me/photos?limit=16'), 'data', array());

  // Here is an example of a FQL call that fetches all of your friends that are
  // using this app
  $app_using_friends = $facebook->api(array(
    'method' => 'fql.query',
    'query' => 'SELECT uid, name FROM user WHERE uid IN(SELECT uid2 FROM friend WHERE uid1 = me()) AND is_app_user = 1'
  ));
}

// Fetch the basic info of the app that they are using
$app_info = $facebook->api('/'. AppInfo::appID());

$app_name = idx($app_info, 'name', '');

// Get comments from friend on posts
function get_comments_from_friend_on_posts() {
  $temp = 'Inside get_comments from friend on posts';
  $jsfunc = '<script>displayMessage("' . $temp . '");</script>';
  echo $jsfunc;
}

?>

<!DOCTYPE html>
<html xmlns:fb="http://ogp.me/ns/fb#" lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=2.0, user-scalable=yes" />

    <title><?php echo he($app_name); ?></title>
    <link rel="stylesheet" href="stylesheets/screen.css" media="Screen" type="text/css" />
    <link rel="stylesheet" href="stylesheets/mobile.css" media="handheld, only screen and (max-width: 480px), only screen and (max-device-width: 480px)" type="text/css" />

    <!--[if IEMobile]>
    <link rel="stylesheet" href="mobile.css" media="screen" type="text/css"  />
    <![endif]-->

    <!-- These are Open Graph tags.  They add meta data to your  -->
    <!-- site that facebook uses when your content is shared     -->
    <!-- over facebook.  You should fill these tags in with      -->
    <!-- your data.  To learn more about Open Graph, visit       -->
    <!-- 'https://developers.facebook.com/docs/opengraph/'       -->
    <meta property="og:title" content="<?php echo he($app_name); ?>" />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="<?php echo AppInfo::getUrl(); ?>" />
    <meta property="og:image" content="<?php echo AppInfo::getUrl('/logo.png'); ?>" />
    <meta property="og:site_name" content="<?php echo he($app_name); ?>" />
    <meta property="og:description" content="My first app" />
    <meta property="fb:app_id" content="<?php echo AppInfo::appID(); ?>" />

	<h1> Sentiment among facebook friends </h1>

    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
    <script type="text/javascript" src="./javascript/jquery-1.7.1.min.js"></script>
    <!--<script type="text/javascript" src="facebook.js"></script>-->
    <script type="text/javascript" src="http://www.google.com/intl/en/chrome/assets/common/js/chrome.min.js"></script>

    <link rel="stylesheet" href="./stylesheets/ui-lightness/jquery-ui-1.10.3.custom.css" type="text/css" media="screen" />
    <script type="text/javascript" src="./scripts/jquery-ui-1.10.3.custom.js"></script>

    <!-- Sources for post_tagger-->
    <script type="text/javascript" src="./pos_tagger/lexer.js"></script>
    <script type="text/javascript" src="./pos_tagger/lexicon.js_"></script>
    <script type="text/javascript" src="./pos_tagger/POSTagger.js"></script>
    <!-- End sources for post_tagger -->

    <!-- Sources for senti_meter -->
    <script type="text/javascript" src="./d3_js/d3.js"></script>
    <script type="text/javascript" src="gauge.js"></script>
    <!-- End sources for senti_meter -->

    <script type="text/javascript">

      // Global variables
      var final_senti = 0.5;
      var senti_friend_id = '<?echo $user_id; ?>';
      var total_samples = 0;

      // Script for senti_meter
      var gauges = [];

      function createGauge(name, label, min, max) {
        var config = 
	{
	  size: 360, //120,
	  label: label,
	  min: undefined != min ? min : 0,
 	  max: undefined != max ? max : 100,
	  minorTicks: 5
	}
				
	var range = config.max - config.min;
	//config.yellowZones = [{ from: config.min + range*0.75, to: config.min + range*0.9 }];
	//config.redZones = [{ from: config.min + range*0.9, to: config.max }];
				
	gauges[name] = new Gauge("senti_meter", config);
	gauges[name].render();
      }

      function createGauges() {
        createGauge("m", "", 0, 1);
	//createGauge("cpu", "CPU");
	//createGauge("network", "Network");
	//createGauge("test", "Test", -50, 50 );
      }

      function updateGauges() {
	for (var key in gauges) {
	  var value = final_senti; //getRandomValue(gauges[key])
	  gauges[key].redraw(value);
	}
      }

      function getRandomValue(gauge) {
	var overflow = 0; //10;
	return gauge.config.min - overflow + (gauge.config.max - gauge.config.min + overflow*2) *  Math.random();
      }
			
      function initialize() {
	createGauges();
	//setInterval(updateGauges, 5000);
      }

      // End of script for senti_meter

      function displayMessage(response) {
        alert('message: ' + response);
      }

      var map_pos_tagger = {};
      map_pos_tagger["CC"] = "i";
      map_pos_tagger["CD"] = "i";
      map_pos_tagger["DT"] = "i";
      map_pos_tagger["EX"] = "i";
      map_pos_tagger["FW"] = "i";
      map_pos_tagger["IN"] = "i";
      map_pos_tagger["JJ"] = "a";
      map_pos_tagger["JJR"] = "a";
      map_pos_tagger["JJS"] = "a";
      map_pos_tagger["LS"] = "i";
      map_pos_tagger["MD"] = "r";
      map_pos_tagger["NN"] = "n";
      map_pos_tagger["NNP"] = "n";
      map_pos_tagger["NNPS"] = "n";
      map_pos_tagger["NNS"] = "n";
      map_pos_tagger["POS"] = "n";
      map_pos_tagger["PDT"] = "i";
      map_pos_tagger["PP$"] = "n";
      map_pos_tagger["PRP"] = "n";
      map_pos_tagger["RB"] = "r";
      map_pos_tagger["RBR"] = "r";
      map_pos_tagger["RBS"] = "r";
      map_pos_tagger["RP"] = "v";
      map_pos_tagger["SYM"] = "i";
      map_pos_tagger["TO"] = "i";
      map_pos_tagger["UH"] = "i";
      map_pos_tagger["VB"] = "v";
      map_pos_tagger["VBD"] = "v";
      map_pos_tagger["VBG"] = "v";
      map_pos_tagger["VBN"] = "v";
      map_pos_tagger["VBP"] = "v";
      map_pos_tagger["VBZ"] = "v";
      map_pos_tagger["WDT"] = "i";
      map_pos_tagger["WP"] = "n";
      map_pos_tagger["WP$"] = "n";
      map_pos_tagger["WRB"] = "r";
      map_pos_tagger[","] = "i";
      map_pos_tagger["."] = "i";
      map_pos_tagger[":"] = "i";
      map_pos_tagger["$"] = "i";
      map_pos_tagger["#"] = "i";
      map_pos_tagger['"'] = "i";
      map_pos_tagger["("] = "i";
      map_pos_tagger[")"] = "i";

      // Compute final_sentiment
      function get_final_sentiment(poss,negs,objs) {
        //return ((poss + (1-negs) + (objs-0.5)) - 0.5) / 2;
	var dev_objs = Math.abs(objs-0.5);
	var dev_poss = Math.abs(poss-0.5);
	var dev_negs = Math.abs(negs-0.5);
	if (poss > negs) {
	  return objs + poss;
	} else {
	  return objs + negs;
	}
	return 0.5;
      }

      function alertResponse(response) {

        // Result
	var senti_val = 0;
	final_senti = 0;

        // pos_tagger
	var str_to_analyze = response;
	var words = new Lexer().lex(str_to_analyze);
	var taggedWords = new POSTagger().tag(words);

	var str_to_send = {}; //new Array();

	for (i in taggedWords) {
	  var taggedWord = taggedWords[i];
	  var word = taggedWord[0].toLowerCase();
	  var tag = taggedWord[1];
	  if (map_pos_tagger[word] == "i") {
	    //alert("word: " + word + ", pos: " + tag + ", map_pos: " + map_pos_tagger[word]);
	  } else {
	    str_to_send[word] = map_pos_tagger[tag];
	    //alert("word: " + word + ", pos: " + tag + ", map_pos: " + map_pos_tagger[tag]);
	  }
	}
	
        //alert('Alert response: ' + response);
	//alert('Talking to get_sentiment.php');

	// Start talking

	var str_array = "ajax";
	//var str_array_encoded = JSON.stringify({ID: str_array});
	var str_array_encoded = JSON.stringify({ID: str_to_send});
	$.ajax({
	    type: "POST",
	    dataType: "json",
	    data: {'data': str_array_encoded},
	    url: './get_sentiment.php',
	    success: function(data) {
	      var i = 0;
	      if (data == false) alert('false_');
	      else {
	        if (data == null) {
		  alert('null_');
		} else {
	          for (var key in data) {
	            //alert(i + ': ' + '; key: ' + key + '; data[key]: ' + data[key]);
		    i++;
		    senti_val = data[key];
		    logResponse(senti_val);
	          }
		  total_samples++;
		  final_senti += get_final_sentiment(data["poss"],data["negs"],data["objs"]);
		  if (final_senti < 0.5)
		    final_senti = (final_senti + 0.5)*(total_samples-1);

		  if (total_samples > 1) final_senti /= (total_samples);

		  logResponse("final_s: " + final_senti);
		  updateGauges();
		}
	      }

	    },
	    error: function(msg) {
	      alert('error: ' + msg);
	    }
	});
	updateGauges();
	// End talking

      }

      function logResponse(response) {
        if (console && console.log) {
          console.log('The response was: ', response);
        }
      }

      // Friends list
      function list_friends() {
      }
      // Append friends
      function populate_friends(frnd_usr_id, frnd_usr_name) {
        $("#list_friends_select_id").append($("<option value='" + frnd_usr_id + "'>" + frnd_usr_name + "</option>"));
      }

      $(function(){
        // Set up so we handle click on the buttons
        $('#postToWall').click(function() {
          FB.ui(
            {
              method : 'feed',
              link   : $(this).attr('data-url')
            },
            function (response) {
              // If response is null the user canceled the dialog
              if (response != null) {
                logResponse(response);
              }
            }
          );
        });

        $('#sendToFriends').click(function() {
          FB.ui(
            {
              method : 'send',
              link   : $(this).attr('data-url')
            },
            function (response) {
              // If response is null the user canceled the dialog
              if (response != null) {
                logResponse(response);
              }
            }
          );
        });

        $('#sendRequest').click(function() {
          FB.ui(
            {
              method  : 'apprequests',
              message : $(this).attr('data-message')
            },
            function (response) {
              // If response is null the user canceled the dialog
              if (response != null) {
                logResponse(response);
              }
            }
          );
        });
      });
    </script>

    <!--[if IE]>
      <script type="text/javascript">
        var tags = ['header', 'section'];
        while(tags.length)
          document.createElement(tags.pop());
      </script>
    <![endif]-->
  </head>
  <body>
    <div id="fb-root"></div>
    <script type="text/javascript">

      function fb_api_js () {
        //alert('in fb_api_js');
	var my_id;
	final_senti = 0.5;
	//updateGauges();
	//final_senti = 0;
        FB.api('/me', function(response) {
    	    //alert("Your friend's name is " + response.name);
	    my_id = response.id;
  	});

	// Get comments from friend on posts
	FB.api('/me/posts', function(response) {
	  //logResponse('FB.api_posts');
	  //logResponse(response);
	  var posts_data = response.data;
	  var posts_data_length = posts_data.length;
	  for (var i = 0; i < posts_data_length; i++) {
	    if (!posts_data[i].comments) {
	      //logResponse("NULL_");
	    }
	    else {
	      var comments = posts_data[i].comments.data;
	      for (var j = 0; j < comments.length; j++) {
	        if (comments[j].from.id === senti_friend_id) {
		  alertResponse(comments[j].message);
		}
	      }
	    }
	  }
	});

	// Get comments from friend on statuses
	FB.api('/me/statuses', function(response) {
	  //logResponse('FB.api_statuses');
	  //logResponse(response);
	  var statuses_data = response.data;
	  for (var i = 0; i < statuses_data.length; i++) {
	    if (!statuses_data[i].comments) {
	      //logResponse("NULL_");
	    }
	    else {
	      var comments = statuses_data[i].comments.data;
	      //logResponse(comments);
	      for (var j = 0; j < comments.length; j++) {
	        if (comments[j].from.id === senti_friend_id) {
		  alertResponse(comments[j].message);
		}
	      }
	    }
	  }
	});

	// Get inbox messages
	FB.api('/me/inbox', function(response) {
	  logResponse('FB.api_inbox');
	  logResponse(response);
	  var inbox_data = response.data;
	  logResponse('inbox_data.length: ' + inbox_data.length);
	  
	  for (var i = 0; i < inbox_data.length; i++) {
	    var check_this_inbox_item = 0;
	    var to_data = inbox_data[i].to.data;
	    for (var j = 0; j < to_data.length; j++) {
	      //logResponse('to_data_id: ' + to_data[j].id);
	      //logResponse('senti_friend_id: ' + senti_friend_id);
	      if (to_data[j].id === senti_friend_id) {
	        check_this_inbox_item = 1;
		logResponse('found matchsenti_friend_id: ');
		break;
	      }
	    }
	    
	    if (check_this_inbox_item === 1) {
	      for (var j = 0; j < inbox_data[i].comments.data.length; j++) {
	        var comment = inbox_data[i].comments.data[j];
		//logResponse(comment);
		if (comment.from.id === senti_friend_id) {
		  //logResponse(comment.message);
		  //displayMessage(comment.message);
		  var msg = comment.message;
		  alertResponse(msg);
		}
	      }
	    }
	    
	  }
	  
	});

	// Get inbox messages and analyze my responses
	FB.api('/me/inbox', function(response) {
	  logResponse('FB.api_inbox');
	  logResponse(response);
	  var inbox_data = response.data;
	  logResponse('inbox_data.length: ' + inbox_data.length);
	  
	  for (var i = 0; i < inbox_data.length; i++) {
	    var check_this_inbox_item = 0;
	    var to_data = inbox_data[i].to.data;
	    for (var j = 0; j < to_data.length; j++) {
	      //logResponse('to_data_id: ' + to_data[j].id);
	      //logResponse('senti_friend_id: ' + senti_friend_id);
	      if (to_data[j].id === senti_friend_id) {
	        check_this_inbox_item = 1;
		logResponse('found matchsenti_friend_id: ');
		break;
	      }
	    }
	    
	    if (check_this_inbox_item === 1) {
	      for (var j = 0; j < inbox_data[i].comments.data.length; j++) {
	        var comment = inbox_data[i].comments.data[j];
		//logResponse(comment);
		if (comment.from.id === my_id) {
		  //logResponse(comment.message);
		  //displayMessage(comment.message);
		  var msg = comment.message;
		  alertResponse(msg);
		}
	      }
	    }
	    
	  }
	  
	});

      };

      var IsConnected = false;
      var show_wallspace = true;
      var show_additionalspace = true;

      function fblogout() {
        FB.logout( function(response) {
	});
      };

      window.fbAsyncInit = function() {
        FB.init({
          appId      : '<?php echo AppInfo::appID(); ?>', // App ID
          channelUrl : '//<?php echo $_SERVER["HTTP_HOST"]; ?>/channel.html', // Channel File
          status     : true, // check login status
          cookie     : true, // enable cookies to allow the server to access the session
          xfbml      : true // parse XFBML
        });

        // Listen to the auth.login which will be called when the user logs in
        // using the Login button
	<?php if (isset($basic)) { ?>
 	FB.Event.subscribe('auth.authResponseChange', function(response) {
	  console.log(response);
	  console.log(response.authResponse.accessToken);
    	  if (response.status === 'connected') {
	    access_token = response.authResponse.accessToken;
	    IsConnected = true;
    	  } else if (response.status === 'not_authorized') {
      	    FB.login();
    	  } else {
      	    FB.login();
    	  }

	  // Populate friends
	  list_friends();
	  
	  // Initialize senti_meter
	  initialize();

  	});
	<?php } ?>
	
        FB.Event.subscribe('auth.login', function(response) {
          window.location = window.location;
	  if (response.status === 'connected') {
	    access_token = response.authResponse.accessToken;
	    IsConnected = true;
	  }
    	  else if (response.status === 'not_authorized') {
	    FB.login();
	  }
	  else {
	    FB.login();
	  }
        });

      };

      $(document).ready( function() {

        // Switch friends upon selection
	$('#list_friends_select_id').change( function() {
	  if ($(this).attr('value') !== 'no_select') {
	    senti_friend_id = $(this).attr('value');
	    //alert('senti_friend_id: ' + senti_friend_id);
	  }
	  total_samples = 0;
	  final_senti = 0.5;
	  updateGauges();
	  fb_api_js();
	});

	// toggle show/hide of news feed
	$('.wall-i').bind('click', function () {
	  if (show_wallspace) {
	    show_wallspace = false;
	    $('div.wall-i').html('<img src="./images/me/icon-w.png">');
	    //$('div.WallSpace').hide();
	    $('div.user_home').hide();
	  } else {
	    show_wallspace = true;
	    $('div.wall-i').html('<img src="./images/me/icon-w-glow.png">');
	    //$('div.WallSpace').show();
	    $('div.user_home').show();
	  }
	});

      }); // end document.ready

      // Load the SDK Asynchronously
      (function(d, s, id) {
        var js, fjs = d.getElementsByTagName(s)[0];
        if (d.getElementById(id)) return;
        js = d.createElement(s); js.id = id;
        js.src = "//connect.facebook.net/en_US/all.js";
        fjs.parentNode.insertBefore(js, fjs);
      }(document, 'script', 'facebook-jssdk'));
    </script>

    <!-- Add here -->

    <?php if (isset($basic)) { ?>

      <!--
      <div class="DockSpace">
      </div>
      -->

      <div class="WallSpace" id="wallspace-id">
	<p>
	  <fb:login-button show-faces="true" width="200" max-rows="1"></fb:login-button>
	</p>
	<p>
	  <button type="button" onclick="fblogout()">Logout</button>
	</p>
        <p> Hi <?php echo he(idx($basic, 'name')); ?> </p>

	<div id='list_friends_div_id'>
	  <select id='list_friends_select_id'>
	    <option value='no_select' selected> --Choose a friend-- </option>
	  </select>
	</div>
	<?php
	  foreach ($friends as $frnd) {
	    $jsfunc = '<script>populate_friends("' . $frnd['id'] . '","' . $frnd['name'] .  '");</script>';
	    echo $jsfunc;
	  }
	?>

	<!--
	<a href="#" class="facebook-button speech-bubble" id="sendToFriends" data-url="<?php echo AppInfo::getUrl(); ?>">
          <span class="speech-bubble">Send Message</span>
        </a>
	-->

	<div class="user_home" id="user_home_id" style="background-color: #b0c4de">

	  <!-- Display meters -->
	  <div>
            <span id="senti_meter"></span>
    	  </div>
	  <!-- End display meters -->

	  <!--
	  <?php
	    $ret_home = $facebook->api('/me/home', 'GET');
	  ?>
	  <?php
	    //echo("<p>".$ret_home['data'][0]['id']."</p>");
	    foreach ($ret_home['data'] as $item) {
	      //echo("<p>" . $item['id'] . "</p>");
	      if ($item['status_type'] === "added_photos") {
	        $friend_basic = $facebook->api('/'.$item['from']['id'], 'GET');
	        echo("<p><a href=" . $friend_basic['link'] . ">" . $item['from']['name'] . "</a>: added a photo</p>");
	        echo("<p><img src=" . $item['picture'] . "></p>");
		//echo $item['picture'];
	      }
	      if ($item['type'] === "status") {
	        $friend_basic = $facebook->api('/'.$item['from']['id'], 'GET');
		//var msg_status = $item['message'];
		if ( strlen($item['message']) > 0) {
	          echo("<p><a href=" . $friend_basic['link'] . ">" . $item['from']['name'] . "</a>: says</p>");
	          echo("<p>" . $item['message'] . "</p>");
		}
	      }
	    }
	  ?>
	  -->

	  <!-- Get comments from friend on posts -->
	  <?php
	    $frnd_id = $senti_friend_id; // Analyze comments from this friend

	    $ret_posts = $facebook->api('/me/posts', 'GET');
	    foreach ($ret_posts['data'] as $item) {
	      if ($item['comments'] === null) {}
	      else {
	        foreach ($item['comments']['data'] as $comment) {
		  if ($comment['from']['id'] === $frnd_id) {
		    $jsfunc = '<script>alertResponse("' . $comment['message'] . '");</script>';
		    echo $jsfunc;
		  }
		} // end foreach comment
	      } // end if ($item['comments']===null)
	    } // end foreach
	  ?>

	  <!-- Get comments from friend on statuses -->
	  <?php
	    $frnd_id = $senti_friend_id;

	    $ret_statuses = $facebook->api('/me/statuses/', 'GET');
	    foreach ($ret_statuses['data'] as $item) {
	      if ($item['comments'] === null) {}
	      else {
	        foreach ($item['comments']['data'] as $comment) {
		  if ($comment['from']['id'] === $frnd_id) {
		    $jsfunc = '<script>alertResponse("' . $comment['message'] . '");</script>';
		    echo $jsfunc;
		  }
		} // end foreach comment
	      } // end if ($item['comments']===null)
	    } // end foreach statuses
	  ?>

	  <!-- Get inbox messages -->
	  <?php
	    $frnd_id = $senti_friend_id;

	    $jsfunc = '<script>displayMessage("' . $frnd_id . '");</script>';
	    //echo $jsfunc;

	    $ret_inbox = $facebook->api('/me/inbox', 'GET');
	    foreach ($ret_inbox['data'] as $item) {
	      $check_this_inbox_item = 0;
	      $to_data = $item['to'];
	      foreach ($to_data['data'] as $to_data_item) {
	        if ($to_data_item['id'] === $frnd_id) {
		  $check_this_inbox_item = 1;
		  break;
		}
	      } // end foreach to_data_item
	      if ($check_this_inbox_item === 1) {
	        foreach ($item['comments']['data'] as $comment) {
		  if ($comment['from']['id'] === $frnd_id) {
		    $jsfunc = '<script>alertResponse("' . $comment['message'] . '");</script>';
		    echo $jsfunc;
		  } // end if comment
		} // end foreach inbox comment
	      } // end check_this_inbox_item
	    } // end foreach inbox item
	  ?>

	  <!-- Get inbox messages and analyze my responses -->
	  <?php
	    $frnd_id = $senti_friend_id;

	    $jsfunc = '<script>displayMessage("' . $frnd_id . '");</script>';
	    //echo $jsfunc;

	    $ret_inbox = $facebook->api('/me/inbox', 'GET');
	    foreach ($ret_inbox['data'] as $item) {
	      $check_this_inbox_item = 0;
	      $to_data = $item['to'];
	      foreach ($to_data['data'] as $to_data_item) {
	        if ($to_data_item['id'] === $frnd_id) {
		  $check_this_inbox_item = 1;
		  break;
		}
	      } // end foreach to_data_item
	      if ($check_this_inbox_item === 1) {
	        foreach ($item['comments']['data'] as $comment) {
		  if ($comment['from']['id'] === $user_id) {
		    $jsfunc = '<script>alertResponse("' . $comment['message'] . '");</script>';
		    echo $jsfunc;
		  } // end if comment
		} // end foreach inbox comment
	      } // end check_this_inbox_item
	    } // end foreach inbox item
	  ?>

	  <!--<script>alertResponse('Hello from html');</script>-->
	  <?php
	    $test_var = "life is great and i am feeling good and happy and i am smiling";
	    $jsfunc = '<script>alertResponse("' . $test_var . '");</script>';
	    echo $jsfunc;
	  ?>

	</div>

      </div>

      <!--
      <div class="AdditionalSpace">
      </div>
      -->

    <?php } else { ?>
      <div>
        <!--
	<h1>You are not logged in... please login...</h1>
	-->
	<!--
        <div class="fb-login-button" data-scope="user_likes,user_photos,user_actions.news,read_stream,publish_stream"></div>
	-->
	<fb:login-button show-faces="true" width="200" max-rows="1" scope='user_likes,user_photos,user_actions.news,read_stream,publish_stream,publish_actions,user_location,xmpp_login,user_online_presence,read_mailbox' perms='publish_stream'></fb:login-button>
      </div>
    <?php } ?>

    <!-- End Add here -->

  </body>
</html>
