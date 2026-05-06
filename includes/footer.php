<?php if (!empty($_SESSION['user_id'])): ?>
    </main>
</div>
<?php else: ?>
</main>
<?php endif; ?>

<footer class="lendi-footer mt-5">
    <div class="container py-4">

        <div class="row g-4">

            <!-- ABOUT -->
            <div class="col-md-5">
                <h5 class="fw-bold">Lendi Institute of Engineering &amp; Technology</h5>
                <p class="mb-2">
                    Leave Management Portal for students, faculty, HOD, and principal approvals.
                </p>
                <small class="text-light opacity-75">
                    Ensuring transparent, timely, and well-documented leave workflows.
                </small>
            </div>

            <!-- QUICK LINKS -->
            <div class="col-md-3">
                <h5 class="fw-bold">Quick Links</h5>
                <ul class="list-unstyled">

                    <?php if (!empty($_SESSION['user_id'])): ?>
                        <li><a href="<?= APP_ROOT ?>/index.php" class="footer-link">Dashboard</a></li>
                        <li><a href="<?= APP_ROOT ?>/student/apply_leave.php" class="footer-link">Apply Leave</a></li>
                        <li><a href="<?= APP_ROOT ?>/approver/dashboard.php" class="footer-link">Approver Desk</a></li>
                        <li><a href="<?= APP_ROOT ?>/auth/logout.php" class="footer-link">Logout</a></li>
                    <?php else: ?>
                        <li><a href="<?= APP_ROOT ?>/auth/login.php" class="footer-link">Login</a></li>
                    <?php endif; ?>

                </ul>
            </div>

            <!-- INFO -->
            <div class="col-md-4">
                <h5 class="fw-bold">Institute Desk</h5>
                <small class="d-block text-light opacity-75">Academic Administration</small>
                <small class="d-block text-light opacity-75">Student Affairs</small>
                <small class="d-block text-light opacity-75 mt-2">
                    Official: <a href="https://lendi.edu.in" target="_blank" class="footer-link">lendi.edu.in</a>
                </small>
            </div>

        </div>

        <!-- BOTTOM -->
        <div class="text-center mt-4 small text-light opacity-75">
            &copy; <?= date('Y') ?> Lendi Institute of Engineering &amp; Technology
        </div>

    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>