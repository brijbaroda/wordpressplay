    <div id="feature" class="site-slider">
    
    	<div class="responsive-container">
        
        	<div class="site-slider-custom-header">
                    
                    <?php
                    
                    if (get_post_meta($post->ID, 'CustomBanner', true) != "") {
    
?>
                        <img src='<?php echo get_post_meta($post->ID, 'CustomBanner', true); ?>' />
                        
                   <?php } 
                   else
                   {
                       ?>
                        		<img src="<?php header_image(); ?>" height="<?php echo get_custom_header()->height; ?>" width="<?php echo get_custom_header()->width; ?>" alt="" />
                  <?php }  
                   
                   
                   ?>
                    
                    
                    
       
       

    		</div><!-- .site-slider-custom-header --> 
                
    	</div><!-- #Responsive-Container -->           
    
    </div><!-- #banner -->