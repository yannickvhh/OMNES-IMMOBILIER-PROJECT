    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-dark text-white">
        <div class="container">
            <div class="row">
                <div class="col-md-4 footer-column mb-3">
                    <h6>Notre Éthique</h6>
                    <p>Tous nos collaborateurs sont disponibles sur les créneaux libres affichés dans leur planning. Prenez rendez-vous quand cela vous convient !</p>
                </div>
                <div class="col-md-4 footer-column mb-3">
                    <h6>Liens Rapides</h6>
                    <ul class="list-unstyled footer-links">
                        <li><a href="<?php echo $path_prefix; ?>index.php" class="text-white">Accueil</a></li>
                        <li><a href="<?php echo $path_prefix; ?>tout-parcourir.php" class="text-white">Tout Parcourir</a></li>
                        <li><a href="<?php echo $path_prefix; ?>recherche.php" class="text-white">Recherche</a></li>
                        <li><a href="<?php echo $path_prefix; ?>rendez-vous.php" class="text-white">Rendez-vous</a></li>
                        <li><a href="<?php echo $path_prefix; ?>votre-compte.php" class="text-white">Votre Compte</a></li>
                    </ul>
                </div>
                <div class="col-md-4 footer-column mb-3">
                    <h6>Contact</h6>
                    <p>37, quai de Grenelle, 75015 Paris, France<br>
                    info@omnesimmobilier.ece.fr<br>
                    +33 1 02 03 04 05<br>
                    +33 1 03 02 05 04</p>
                </div>
            </div>
            
            <div class="map-container my-3" style="height: 200px; /* Adjust height as needed */">
                <iframe src="https://www.google.com/maps?q=37+quai+de+Grenelle,+75015+Paris&output=embed" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
            </div>
            
            <div class="text-center footer-bottom pt-3 border-top border-secondary">
                <p>&copy; <?php echo date("Y"); ?> OMNES IMMOBILIER | Tous droits réservés</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Your custom JS -->
    <script src="<?php echo $path_prefix; ?>js/main.js"></script> 

</body>
</html> 