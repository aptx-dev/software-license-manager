<?php

// Prevent direct access to the file
if (!defined('WPINC')) {
    die;
}

/**
 * Display the About menu for SLM
 */
function slm_about_menu()
{
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">
            <?php esc_html_e('SLM - About', 'slm-plus'); ?>
        </h1>

        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-2">
                <div id="post-body-content">
                    <div class="postbox">
                        <h2 class="hndle"><?php esc_html_e('Credits and Authors', 'slm-plus'); ?></h2>
                        <div class="inside">
                            <p><?php esc_html_e('SLM is a comprehensive software license management solution for your web applications, supporting WordPress plugins, themes, applications, and WooCommerce.', 'slm-plus'); ?></p>
                            <table class="widefat fixed striped slm-about-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Information', 'slm-plus'); ?></th>
                                        <th><?php esc_html_e('Details', 'slm-plus'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><?php esc_html_e('Authors', 'slm-plus'); ?></td>
                                        <td>
                                            <a href="https://github.com/michelve/software-license-manager" target="_blank" rel="noopener noreferrer">
                                                Michel Velis
                                            </a> 
                                            <?php esc_html_e('and', 'slm-plus'); ?>
                                            <a href="https://github.com/Arsenal21/software-license-manager" target="_blank" rel="noopener noreferrer">
                                                tipsandtricks
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><?php esc_html_e('Help and Support', 'slm-plus'); ?></td>
                                        <td>
                                            <a href="https://github.com/michelve/software-license-manager/issues" target="_blank" rel="noopener noreferrer">
                                                <?php esc_html_e('Submit a request', 'slm-plus'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><?php esc_html_e('API Demos', 'slm-plus'); ?></td>
                                        <td>
                                            <a href="https://documenter.getpostman.com/view/307939/6tjU1FL?version=latest" target="_blank" rel="noopener noreferrer">
                                                <?php esc_html_e('Postman Demos', 'slm-plus'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div> <!-- end postbox -->
                </div> <!-- end post-body-content -->
            </div> <!-- end post-body -->
        </div> <!-- end poststuff -->
    </div> <!-- end wrap -->
    <?php
}
