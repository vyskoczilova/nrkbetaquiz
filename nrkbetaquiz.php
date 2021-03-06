<?php
/*
Plugin Name: NRKBeta Know2Comment
Version: 1.0.1
Plugin URI: https://nrkbeta.no/
Author: Henrik Lied and Eirik Backer, Norwegian Broadcasting Corporation
Description: Require the user to answer a quiz to be able to post comments.
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define('NRKBCQ', 'nrkbetaquiz');
define('NRKBCQ_NONCE', NRKBCQ . '-nonce');

// Load textdomain
add_action( 'init', 'nrkbetaquiz_localize_plugin' );
function nrkbetaquiz_localize_plugin() {
    load_plugin_textdomain( 'nrkbetaquiz', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

add_action('wp_enqueue_scripts', function(){
  if( comments_open() ) {
    global $post;
    wp_register_script( NRKBCQ, plugins_url('nrkbetaquiz.js', __FILE__) );
		wp_enqueue_script( NRKBCQ);
		$params = array(
			'questions'       => esc_attr(rawurlencode(json_encode(get_post_meta($post->ID, NRKBCQ)))),
			'i18n_error'     => __('You have not answered the quiz correctly. Try again.', NRKBCQ),		
		);
		wp_localize_script( NRKBCQ, 'nrkbcq', $params );
    wp_enqueue_style(NRKBCQ, plugins_url('nrkbetaquiz.css', __FILE__));
  }
});

add_action('comment_form_before', 'nrkbetaquiz_form');
function nrkbetaquiz_form(){ ?>
  <div class="<?php echo NRKBCQ; ?>">
    <h2><?php _e('Would you like to comment? Please answer some quiz questions from the story.', NRKBCQ); ?></h2>
    <p>
      <?php _e('We care about our comments.', NRKBCQ); ?>
      <?php _e("That's why we want to make sure that everyone who comments have actually read the story.", NRKBCQ); ?>
      <?php _e('Answer a couple of questions from the story to unlock the comment form.', NRKBCQ); ?>
    </p>
    <noscript><?php printf( __( 'Please %1$senable javascript%2$s to comment', NRKBCQ ) , '<a href="http://enable-javascript.com/" target="_blank" style="text-decoration:underline">', '</a>' ); ?></noscript>
  </div>
<?php }

add_action('add_meta_boxes', 'nrkbetaquiz_add');
function nrkbetaquiz_add(){
  add_meta_box(NRKBCQ, 'CommentQuiz', 'nrkbetaquiz_edit', 'post', 'side', 'high');
}

function nrkbetaquiz_edit($post){
  $questions = array_pad(get_post_meta($post->ID, NRKBCQ), 1, array());
  $addmore = esc_html(__('Add question +', NRKBCQ));
  $correct = esc_html(__('Correct', NRKBCQ));
  $answer = esc_attr(__('Answer', NRKBCQ));

  foreach($questions as $index => $question){
    $title = __('Question', NRKBCQ) . ' ' . ($index + 1);
    $text = esc_attr(empty($question['text'])? '' : $question['text']);
    $name = NRKBCQ . '[' . $index . ']';

    echo '<div style="margin-bottom:1em;padding-bottom:1em;border-bottom:1px solid #eee">';
    echo '<label><strong>' . $title . ':</strong><br><input type="text" name="' . $name . '[text]" value="' . $text . '"></label>';
    for($i = 0; $i<3; $i++){
      $check = checked($i, isset($question['correct'])? intval($question['correct']) : 0, false);
      $value = isset($question['answer'][$i])? esc_attr($question['answer'][$i]) : '';

      echo '<br><input type="text" name="' . $name . '[answer][' . $i . ']" placeholder="' . $answer . '" value="' . $value . '">';
      echo '<label><input type="radio" name="' . $name . '[correct]" value="' . $i . '"' . $check . '> ' . $correct . '</label>';
    }
    echo '</div>';
  }
  echo '<button class="button" type="button" data-' . NRKBCQ . '>' . $addmore . '</button>';

  ?><script>
    document.addEventListener('click', function(event){
      if(event.target.hasAttribute('data-<?php echo NRKBCQ; ?>')){
        var button = event.target;
        var index = [].indexOf.call(button.parentNode.children, button);
        var clone = button.previousElementSibling.cloneNode(true);
        var title = clone.querySelector('strong');

        title.textContent = title.textContent.replace(/\d+/, index + 1);
        [].forEach.call(clone.querySelectorAll('input'), function(input){
          input.name = input.name.replace(/\d+/, index);  //Update index
          if(input.type === 'text')input.value = '';      //Reset value
        });
        button.parentNode.insertBefore(clone, button);    //Insert in DOM
      }
    });
  </script>
  <?php wp_nonce_field(NRKBCQ, NRKBCQ_NONCE);
}

add_action('save_post', 'nrkbetaquiz_save', 10, 3);
function nrkbetaquiz_save($post_id, $post, $update){
  if(isset($_POST[NRKBCQ], $_POST[NRKBCQ_NONCE]) && wp_verify_nonce($_POST[NRKBCQ_NONCE], NRKBCQ)){
    delete_post_meta($post_id, NRKBCQ);                         //Clean up previous quiz meta
    foreach($_POST[NRKBCQ] as $k=>$v){
      if($v['text'] && array_filter($v['answer'], 'strlen')){   //Only save filled in questions

        // Sanitizing data input
        foreach ( $v as $key => $value ) {
          $key = wp_kses_post( $key );
          $value = wp_kses_post( $value );
          $v[$key] = $value;
        }

        add_post_meta($post_id, NRKBCQ, $v);
      }
    }
  }
}
