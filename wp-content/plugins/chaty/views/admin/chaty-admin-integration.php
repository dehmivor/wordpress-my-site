<?php
global $wp_version ;
$plugins_allowedtags = array(
    'a'       => array(
        'href'   => array(),
        'title'  => array(),
        'target' => array(),
        'class' => array(),
    ),
    'abbr'    => array( 'title' => array() ),
    'acronym' => array( 'title' => array() ),
    'code'    => array(),
    'pre'     => array(),
    'em'      => array(),
    'strong'  => array(),
    'ul'      => array(),
    'ol'      => array(),
    'li'      => array(),
    'p'       => array(),
    'br'      => array(),
);
?>
<link type="text/css" rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins" />
<div class="chaty-new-widget-wrap">
    <div class="new-widget-wrap">
        <h2 class="text-center chaty-integrate-title-main"><?php esc_html_e( 'Upgrade Now and connect your Chaty form to the following platforms to automatically receive leads', 'chaty' ); ?></h2>
        <div class="chaty-new-widget-row">
            <div class="chaty-features">
                <ul>
                    <li>
                        <div class="elements-int-container chaty-feature">
                            <div class="chaty-feature-top">
                                <img src="<?php echo esc_url(CHT_PLUGIN_URL) ?>admin/assets/images/mailchimp.png" />
                            </div>
                            <div class="feature-title">Connect your forms to Mailchimp</div>
                            <div id="elements-int-container-content feature-description">
                                <p>
                                    <a href="#" class="integrate-element-form">
                                        <?php esc_html_e('Connect', 'chaty')?>
                                    </a>
                                </p>
                            </div>
                        </div>
                        <div class="chaty-integration-button">
                            <a href="<?php echo esc_url(admin_url("admin.php?page=chaty-app-upgrade")); ?>" class="chaty-upgrade-now-button" target="blank">
                                <?php esc_html_e('Upgrade Now', 'chaty') ?>
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M6 12L10 8L6 4" stroke="#092030" stroke-width="1.67" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </a>
                        </div>
                    </li>
                    <li>
                        <div class="elements-int-container chaty-feature">
                            <div class="chaty-feature-top">
                                <img src="<?php echo esc_url(CHT_PLUGIN_URL) ?>admin/assets/images/klaviyo_icon.png" />
                            </div>
                            <div class="feature-title">Connect your forms to Klaviyo</div>
                            <div id="elements-int-container-content feature-description">
                                <p>
                                    <a href="#" class="integrate-element-form">
                                        <?php esc_html_e('Connect', 'chaty')?>
                                    </a>
                                </p>
                            </div>
                        </div>
                        <div class="chaty-integration-button">
                            <a href="<?php echo esc_url(admin_url("admin.php?page=chaty-app-upgrade")); ?>" class="chaty-upgrade-now-button" target="blank">
                                <?php esc_html_e('Upgrade Now', 'chaty') ?>
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M6 12L10 8L6 4" stroke="#092030" stroke-width="1.67" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </a>
                        </div>
                    </li>
                </ul>
                <div class="clear clearfix"></div>
            </div>
        </div>
        <div class="chaty-integration-upgrade-button">
            <a href="<?php echo esc_url(admin_url("admin.php?page=chaty-app-upgrade")); ?>" class="chaty-upgrade-now-button" target="blank">
                <?php esc_html_e('Upgrade Now', 'chaty') ?>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6 12L10 8L6 4" stroke="#092030" stroke-width="1.67" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </a>
        </div>
    </div>
</div>

<style>
    *, ::after, ::before {
        box-sizing: border-box;
    }
    /*New Widget Page css*/
    .chaty-new-widget-wrap {
        padding: 30px;
        margin: 20px auto 0 auto;
        width: 100%;
        font-family: Poppins;
        line-height: 20px;
        display: flex;
        min-height: calc(100vh - 200px);
        align-items: center;
        justify-content: center;
    }
    a.integrate-element-form {
        text-decoration: none;
        font-weight: 600;
        padding: 8px 20px;
        border: solid 1px #49687e;
        color: #49687e;
        border-radius: 4px;
        margin-top: 10px;
        display: inline-flex;
    }
    .new-widget-wrap {
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 24px;
    }
    .chaty-features {
        padding-top: 40px;
        max-width: 776px;
        margin: 0 auto;
    }
    .chaty-upgrade-now-button {
        align-items: center;
        background: linear-gradient(94.73deg,#f8c704 5.32%,#ffab00 56.99%);
        border: 1px solid #0000;
        border-radius: 8px;
        color: #092030;
        display: inline-flex;
        gap: 6px;
        padding: 8px 16px;
        transition: all .2s ease-in-out;
        white-space: nowrap;
        text-decoration: none;
        font-size: 14px;
        line-height: 1.4;
    }
    .chaty-upgrade-now-button:hover, .chaty-upgrade-now-button:focus {
        background: linear-gradient(94.73deg,#f8c704 5.32%,#ffab00 56.99%);
        box-shadow: 0 0 0 3px rgba(255,171,0,.3);
        color: #092030;
        border-radius: 8px;
    }
    .chaty-new-widget-wrap h2.chaty-integrate-title-main {
        font-style: normal;
        font-size: 20px;
        line-height: 1.5;
        color: #1E1E1E;
        margin: 0 auto;
        max-width: 600px;
        position: relative;
        font-weight: 600;
        text-align: center;
    }
    .chaty-features ul {
        margin: 0;
        padding: 0;
    }
    .chaty-features ul li {
        margin: 0;
        width: 50%;
        float: left;
        padding: 10px;
        position: relative;
    }
    .chaty-feature p {
        text-align: center;
    }
    .chaty-feature {
        background: #fff;
        border-radius: 10px;
        padding: 60px 20px 10px 20px;
        height: 100%;
        position: relative;
        box-shadow: 0px 2px 4px rgba(0, 0, 0, 0.06), 0px 4px 6px rgba(0, 0, 0, 0.1);
    }
    .chaty-feature-top {
        width: 73px;
        height: 73px;
        border-radius: 50%;
        position: absolute;
        left: 0;
        right: 0;
        margin: 0 auto;
        top: -25px;
        background: #fff;
        z-index: 11;
        padding: 10px;
        box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.06), 0px 1px 3px rgba(0, 0, 0, 0.1);
    }
    .feature-title {
        font-style: normal;
        font-weight: 400;
        font-size: 16px;
        line-height: 18px;
        color: #64748B;
        margin-bottom: 15px;
        text-align: center;
    }
    .chaty-feature.second {
        min-height: 155px;
    }
    .feature-description {
        font-family: Poppins;
        font-style: normal;
        font-weight: normal;
        font-size: 13px;
        line-height: 18px;
        color: #1E1E1E;
    }
    a.new-upgrade-button {
        display: inline-block;
        margin-top: 15px;
        padding: 8px 20px;
        border: solid 2px rgba(176, 143, 229, 1);
        color: rgba(176, 143, 229, 1);
        font-weight: 600;
        border-radius: 4px;
        font-size: 14px;
        text-decoration: none;
        letter-spacing: 0.6px;
    }
    a.new-upgrade-button:hover {
        background-color: rgba(176, 143, 229, 1);
        color: #ffffff;
    }
    a.new-demo-button {
        height: 40px;
        color: #605DEC;
        border: solid 1px #605DEC;
        border-radius: 100px;
        display: inline-block;
        text-align: center;
        background: #fff;
        line-height: 40px;
        margin: 10px 0 10px 10px;
        padding: 0 25px;
        text-decoration: none;
        width: 165px;
    }
    .chaty-feature.analytics {
        min-height: 115px;
    }
    .chaty-feature-top img {
        width: 100%;
        height: auto;
    }

    .chaty-features ul li:hover .chaty-integration-button{
        display: flex;
    }
    .chaty-features ul li:hover a.new-upgrade-button {
        background-color: rgba(176, 143, 229, 1);
        color: #ffffff;
    }
    .chaty-integration-button {
        display: none;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 9;
        backdrop-filter: blur(2px);
        width: 100%;
        height: 100%;
        align-items: center;
        justify-content: center;
    }
    .chaty-feature input[type="text"] {
        border: 1px solid #E2E8F0;
        color: #9CA3AF;
        font-size: 12px;
    }
    .chaty-feature .button-primary {
        border: 1px solid #3C85F7;
        background-color: transparent;
        color: #3C85F7;
        padding: 5px 17px;
        line-height: 1;
        border-radius: 2px;
    }
    .chaty-feature a.button-primary {
        padding-top: 7px;
    }
    .chaty-feature .button-primary.btn-connected {
        border-color: #057A55;
        color: #057A55;
    }
    .chaty-feature .button-primary.btn-disconnected {
        color: #B91C1C;
        border: 0;
        padding: 0;
        background-color: transparent;
    }
    .chaty-integration-upgrade-button {
        text-align: center;
    }

</style>
