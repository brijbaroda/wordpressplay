<div class="bizfive">

	<div class="bizfive-content-cont">
    		
    		<div class="bizfive-welcome">
    
                    <h2>
                        <?php 
                            if( of_get_option('bfive_welcome_headline') ){
                                echo esc_html( of_get_option('bfive_welcome_headline') );
                            }else {
                                _e('Welcome Headline Comes Here',  'BizSphere');
                            }
                        ?>    
                    </h2>
                    
                    <div class="bizfive-welcome-desc">
                        <?php 
                            if( of_get_option('bfive_welcome_text') ){
                                echo esc_html( of_get_option('bfive_welcome_text') );
                            }else {
                                _e('You can change this text in welcome text box of welcome section block in Biz five tab of theme options page. You can change this text in welcome text box of welcome section block in Biz five tab of theme options page.',  'BizSphere');
                            }
                        ?>                                
                    </div>
                    
			</div><!-- .bizfive-welcome -->
    </div><!-- .bizfive-content-cont -->
    
	   
		
</div><!-- .bizfive -->

<?php if( !of_get_option('show_bfive_posts') || of_get_option('show_bfive_posts') == 'true' ) : ?>
<div class="bizfive">
	
		<?php 
			
			if( 'page' == get_option( 'show_on_front' ) ){	
				get_template_part('index', 'page');
			}else {
				get_template_part('index', 'standard');
			}			 
			
		?>
		
</div><!-- .biz0ne -->
<?php endif; ?> 