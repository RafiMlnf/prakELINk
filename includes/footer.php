</div><!-- /.main-content -->
</div><!-- /.app-container -->

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- App JS -->
<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= time() ?>"></script>

<?php if (isset($extraJs)): ?>
    <script src="<?= BASE_URL ?>/assets/js/<?= $extraJs ?>"></script>
<?php endif; ?>

</body>

</html>