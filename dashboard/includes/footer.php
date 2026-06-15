    <!-- Initialize Lucide Icons -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
            // GSAP fade-in
            if (typeof gsap !== 'undefined') {
                gsap.from('.animate-in', { duration: 0.6, y: 20, opacity: 0, stagger: 0.1, ease: 'power2.out' });
            }
        });
    </script>
</body>
</html>
