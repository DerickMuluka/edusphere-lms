        </section>

        <footer class="app__footer">
            <span>&copy; <?php echo date('Y'); ?> <?php echo e(SITE_NAME); ?>. All rights reserved.</span>
        </footer>
    </div>
</div>

<script>window.APP_BASE = "<?php echo e(SITE_URL); ?>";</script>
<script src="<?php echo e(SITE_URL); ?>/assets/js/core.js"></script>
<?php foreach (($extraJS ?? []) as $js): ?>
    <script src="<?php echo e(SITE_URL . '/assets/js/' . $js); ?>"></script>
<?php endforeach; ?>
</body>
</html>