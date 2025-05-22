<footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>WavePass</h3>
                    <p>Modern attendance tracking solutions for educational institutions of all sizes.</p>
                    <div class="social-links">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    </div>
                    <img src="../imgs/erasmus.png" alt="Erasmus+ Logo" class="erasmus-logo">
                </div>
                
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="help.php"><i class="fas fa-chevron-right"></i> Help Center</a></li>
                        <li><a href="pricing.php"><i class="fas fa-chevron-right"></i> Pricing</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Resources</h3>
                    <ul class="footer-links">
                        <li><a href="blog.php"><i class="fas fa-chevron-right"></i> Blog</a></li>
                        <li><a href="webinars.php"><i class="fas fa-chevron-right"></i> Webinars</a></li>
                        <li><a href="api.php"><i class="fas fa-chevron-right"></i> API Documentation</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Contact Info</h3>
                    <ul class="footer-links">
                        <li><a href="mailto:info@wavepass.com"><i class="fas fa-envelope"></i> info@wavepass.com</a></li>
                        <li><a href="tel:+420733757767"><i class="fas fa-phone"></i> +420 733 757 767</a></li>
                        <li>
                             <a href="https://maps.app.goo.gl/sRryn6QST8gEhF6t5" target="_blank" rel="noopener noreferrer" title="View on Google Maps">
                                <i class="fas fa-map-marker-alt"></i> Sídliště Generála Josefa Kholla 2501, Rakovník
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>© <?php echo date("Y"); ?> WavePass All rights reserved. | <a href="privacy.php">Privacy Policy</a> | <a href="terms.php">Terms of Service</a></p>
            </div>
        </div>
    </footer>

    <style>
        .footer-column .erasmus-logo {
    display: block; /* Aby margin fungoval správně a obrázek byl na vlastním řádku */
    width: 200px;   /* Upravte šířku podle potřeby, např. 150px, 200px, 250px */
    max-width: 100%; /* Zajistí, že obrázek nebude širší než rodičovský kontejner na menších obrazovkách */
    height: auto;   /* Zachová poměr stran obrázku */
    margin-top: 25px; /* Odsazení od sociálních ikon nad ním */
    margin-bottom: 15px; /* Odsazení zespodu, pokud by pod ním něco bylo v tomto sloupci */
    /* Pokud byste chtěli obrázek vycentrovat v rámci sloupce: */
    /* margin-left: auto; */
    /* margin-right: auto; */
}
        .footer-column > img { /* Zacílí přímo na <img> tag, který je přímým potomkem .footer-column */
    display: block;
    width: 200px;
    max-width: 100%;
    height: auto;
    margin-top: 25px;
    margin-bottom: 15px;
}
    </style>