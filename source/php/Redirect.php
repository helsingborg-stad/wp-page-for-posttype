<?php

namespace WpPageForPostType;

class Redirect
{

    /**
     * Trigger template redirections
     * @return void
     */
    public function __construct() {
      add_action('template_redirect', array($this, 'redirectFromOrginalArchive')); 
      add_action('template_redirect', array($this, 'redirectFromOrginalSingle')); 
    }

    /**
     * Redirect from single page in old location
     * @return void
     */
    public function redirectFromOrginalSingle() {

      //Check if this is a archive
      if(!is_404()) {
        return; 
      }

      global $wp_query;

      // Bail if page not set
      if(!$pageForPostType = get_option('page_for_' . $wp_query->query['post_type'])) {
        return; 
      }

      // Check that the target page still exists
      if (is_numeric($pageForPostType) && $permalink = get_permalink( $pageForPostType )) {
        
        //Check if the page isen't current
        if(!$this->isCurrentPage($permalink)) {
          wp_redirect($permalink, 302, "X-Redirect-By: WP Page For Posttype (Single)");
          exit; 
        }

      }
    }

    /**
     * Redirect from registrered archive page 
     * @return void
     */
    public function redirectFromOrginalArchive() {

      //Check if this is a archive
      if(!is_archive()) {
        return; 
      }

      global $wp_query;

      // Bail if page not set
      if(!$pageForPostType = get_option('page_for_' . $wp_query->query['post_type'])) {
        return; 
      }

      // Check that the target page still exists
      if (is_numeric($pageForPostType) && $permalink = get_permalink( $pageForPostType )) {
        
        //Check if the page isen't current
        if(!$this->isCurrentPage($permalink)) {
          wp_redirect($permalink, 302, "X-Redirect-By: WP Page For Posttype (Archive)");
          exit; 
        }

      }
    }

    /**
     * Get the current requested url 
     * @return string
     */
    public function getCurrentUrl() {
      global $wp;
      return home_url($wp->request); 
    }

    /**
     * Check if the page is the new target page 
     * @return bool
     */
    public function isCurrentPage($targetPage) {
      $currentPage  = preg_replace('/\?.*/', '', rtrim($this->getCurrentUrl(), "/"));  
      $targetPage   = preg_replace('/\?.*/', '', rtrim($targetPage, "/"));  

      if($currentPage == $targetPage) {
        return true; 
      }
      return false; 
    } 

}
