<?php

class rfw_dock extends WP_Widget {

	public function __construct() {
		// widget actual processes
		parent::__construct(
			'rfw_dock', // Base ID
			__('RSS Feed Widget', 'text_domain'), // Name
			array( 'description' => __( 'A feed widget with sliding effect.', 'text_domain' ), ) // Args
		);
	}
	

	function clean_xhtml($string)
	{
		$string = ereg_replace("<[^>]*>", "", $string);
		$string = preg_replace("@<p[^>]*?>.*?
		@siu", '',$string);
		return $string;
	}


	public function widget( $args, $instance ) {
		// outputs the content of the widget
		 
		 global $wpdb;
		 
		 $limit_feeds = ($instance['number']>0?$instance['number']:1);
		 
		 $title = $instance['title'];
		 
		 $rss_url = $instance['rss_url'];
		 
		 $show_feed_title = $instance['show_feed_title'];
		 
		 $feed_words = ($instance['feed_words']>0?$instance['feed_words']:60);
		 
		 $display_type = $instance['content_display'];
		
		 $html = '<nav class="add-nav widget_dock" id="rfw-authors">';
		 
		 $html.= ($title!='')?'<h2 class="widgettitle">'.$title.'</h2>':'';
		 
		 $rss = fetch_feed($rss_url);
		 
		 if ( ! is_wp_error( $rss ) ){
			 
			$maxitems = $rss->get_item_quantity( $limit_feeds );  
			
			$rss_items = $rss->get_items( 0, $maxitems );
			
			if ( $maxitems > 0 ){
				$html.= '<ul class="rfw_dock">';
           
				foreach ( $rss_items as $item ){
					
					$html.='<li>';
					
					$img = '';
					
					$link = '<a href="'.esc_url( $item->get_permalink() ).'" target="_blank" rel="nofollow">_LINK_TEXT_</a>';
					
					$html.= $show_feed_title?str_replace('_LINK_TEXT_', '<h3 class="entry-title">'.esc_html( $item->get_title() ).'</h3>', $link):'';
					
					$description = $item->get_description();	
					
					
					
					switch($display_type){
						
						case 'text_only':						
							$html.= $this->string_limit_words($this->clean_xhtml($description), $feed_words).' ...';							
						break;
						
						default:
							$html.= $this->string_limit_words($description, $feed_words).' ...';	
						break;
						
						case 'image_only':						
							//preg_match_all('/<img[^>]+>/i', $description, $img); 
							//
							//preg_match_all('/src="([^"]*)"/', $description, $img); 
							preg_match_all('@<img.*src="([^"]*)"[^>/]*/?>@Ui', $description, $img); 
							
							
							if(!empty($img)){
								
								$img = end($img);
								
								if(isset($img[0]) && $img[0]!=''){
									$img = $img[0];
									
									$img = current(explode('?', $img));
									
									
									
								}else{
									$img = '';
								}
							}
						break;		
					}
					
					
				   
					if($img!=''){
					   
						$html.= str_replace('_LINK_TEXT_', '<div class="image_only"><img src="'.$img.'" /></div>', $link);
					
					}
				   
				   
					$html.= '</li>';
          		  
				}
			}
		 }
		
		 
		 $html .= '</ul></nav>';
		 echo $html;
	
	}

	private function string_limit_words($string, $word_limit)
	{
	  $words = explode(' ', $string, ($word_limit + 1));
	  if(count($words) > $word_limit)
	  array_pop($words);
	  return implode(' ', $words);
	}
	
 	public function form( $instance ) {
		// outputs the options form on admin
		$title     = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
		$rss_url     = isset( $instance['rss_url'] ) ? esc_attr( $instance['rss_url'] ) : '';
		$number    = isset( $instance['number'] ) ? absint( $instance['number'] ) : 6;
		
		$show_feed_title = isset( $instance['show_feed_title'] ) ? esc_attr( $instance['show_feed_title'] ) : true;
		$feed_words    = isset( $instance['feed_words'] ) ? absint( $instance['feed_words'] ) : 60;
		$content_display    = isset( $instance['content_display'] ) ? $instance['content_display'] : 'default';
		
		
		
		?>
		<p>
		<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title (optional):' ); ?></label> 
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
        <p><label for="<?php echo $this->get_field_id( 'rss_url' ); ?>"><?php _e('Enter the RSS feed URL here:'); ?></label>
	<input class="widefat" id="<?php echo $this->get_field_id( 'rss_url' ); ?>" name="<?php echo $this->get_field_name( 'rss_url' ); ?>" type="text" value="<?php echo esc_attr( $rss_url ); ?>" /></p>
    
     <p><label for="<?php echo $this->get_field_id( 'content_display' ); ?>"><?php _e( 'Display type:' ); ?></label>
        <select id="<?php echo $this->get_field_id( 'content_display' ); ?>" name="<?php echo $this->get_field_name( 'content_display' ); ?>">
        	<option value="default" <?php echo $content_display=='default'?'selected="selected"':''; ?>>Default</option>
            <option value="text_only" <?php echo $content_display=='text_only'?'selected="selected"':''; ?>>Text Only</option>
            <option value="image_only" <?php echo $content_display=='image_only'?'selected="selected"':''; ?>>Image Only</option>
        </select></p>
        
        <p><label for="<?php echo $this->get_field_id( 'number' ); ?>"><?php _e( 'Number of feeds to show:' ); ?></label>
		<input id="<?php echo $this->get_field_id( 'number' ); ?>" name="<?php echo $this->get_field_name( 'number' ); ?>" type="text" value="<?php echo $number; ?>" size="3" /></p>
        
        <p><label for="<?php echo $this->get_field_id( 'show_feed_title' ); ?>"><?php _e( 'Display feed title (Yes/No):' ); ?></label>
		<input id="<?php echo $this->get_field_id( 'show_feed_title' ); ?>" name="<?php echo $this->get_field_name( 'show_feed_title' ); ?>" type="checkbox" value="true" <?php echo $show_feed_title?'checked="checked"':''; ?>  /></p>
        
        <p><label for="<?php echo $this->get_field_id( 'feed_words' ); ?>"><?php _e( 'No. of feed words to show:' ); ?></label>
		<input id="<?php echo $this->get_field_id( 'feed_words' ); ?>" name="<?php echo $this->get_field_name( 'feed_words' ); ?>" type="text" value="<?php echo $feed_words; ?>" size="3" /></p>
        
        
       
        
		<?php 
	}

	public function update( $new_instance, $old_instance ) {
		// processes widget options to be saved
		
		$instance = array();
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['rss_url'] = strip_tags($new_instance['rss_url']);
		$instance['number'] = (int) $new_instance['number'];
		
		$instance['show_feed_title'] = ($new_instance['show_feed_title']==true);
		$instance['feed_words'] = (int) $new_instance['feed_words'];
		$instance['content_display'] = strip_tags($new_instance['content_display']);
		
		

		return $instance;
		
	}
}
if(!function_exists('rfw_init')){
	function rfw_init(){
		 register_widget( 'rfw_dock' );
		}
	}
?>