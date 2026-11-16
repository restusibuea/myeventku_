    </main>

    <!-- Footer (hilang pas print) -->
    <footer class="bg-white border-t border-slate-200 mt-auto print:hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-slate-500">
                <p class="text-center sm:text-left">
                    &copy; 2026 Sistem Informasi Manajemen Event &amp; Pendaftaran Peserta
                </p>
                <p class="text-center sm:text-right font-medium text-slate-600">
                    Restu Bajawa Erlintua Sibuea &bull; ProyekKu
                </p>
            </div>
        </div>
    </footer>

    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            if (menu) {
                menu.classList.toggle('hidden');
            }
        }
    </script>
</body>
</html>
