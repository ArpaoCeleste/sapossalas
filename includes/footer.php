<footer class="bg-[#151414] relative font-['Poppins'] text-[#b8a5c8]">
    <div class="max-w-[1200px] mx-auto px-6">

        <div class="border-b border-[#5d3e8f] py-12">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center ">

                <div class="flex flex-col md:flex-row items-center md:items-start">
                    <i class="fas fa-map-marker-alt text-[#8c76a8] text-3xl mt-2 mb-2 md:mb-0"></i>
                    <div class="ml-0 md:ml-4">
                        <h4 class="text-[#fbeaff] text-xl font-semibold mb-0.5 text-center">
                            <?php echo isset($t['footer_find_us']) ? $t['footer_find_us'] : 'Encontre-nos'; ?>
                        </h4>
                        <span class="text-[15px]">Sapolandia, Portugal</span>
                    </div>
                </div>

                <div class="flex flex-col md:flex-row items-center md:items-start">
                    <i class="fas fa-phone text-[#8c76a8] text-3xl mt-2 mb-2 md:mb-0"></i>
                    <div class="ml-0 md:ml-4">
                        <h4 class="text-[#fbeaff] text-xl font-semibold mb-0.5">
                            <?php echo isset($t['footer_contact']) ? $t['footer_contact'] : 'Contacte-nos'; ?>
                        </h4>
                        <span class="text-[15px]">+351 123 456 789</span>
                    </div>
                </div>

                <div class="flex flex-col md:flex-row items-center md:items-start">
                    <i class="far fa-envelope-open text-[#8c76a8] text-3xl mt-2 mb-2 md:mb-0"></i>
                    <div class="ml-0 md:ml-4">
                        <h4 class="text-[#fbeaff] text-xl font-semibold mb-0.5">
                            <?php echo isset($t['footer_email']) ? $t['footer_email'] : 'Email'; ?>
                        </h4>
                        <span class="text-[15px]">admin@saposalas.pt</span>
                    </div>
                </div>

            </div>
        </div>

        <div class="py-12">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">

                <div class="flex justify-center lg:justify-start items-center">
                    <a href="<?php echo isset($rootPath) ? $rootPath : '../'; ?>index.php">
                        <img src="<?php echo isset($rootPath) ? $rootPath : '../'; ?>imagens/pngsapo.png"
                            class="max-w-[120px] h-auto transition-transform duration-1000 hover:scale-110" alt="logo">
                    </a>
                </div>

                <div class="flex flex-col items-center">
                    <div class="text-center">
                        <div class="mb-6 relative inline-block">
                            <h3 class="text-[#fbeaff] text-xl font-semibold relative z-10 pb-2">
                                <?php echo isset($t['footer_links']) ? $t['footer_links'] : 'Links Úteis'; ?>
                            </h3>
                            <span
                                class="absolute left-1/2 -translate-x-1/2 -bottom-0 h-[2px] w-[50px] bg-[#8c76a8]"></span>
                        </div>

                        <ul class="flex flex-col gap-3 text-center">
                            <li>
                                <a href="<?php echo isset($rootPath) ? $rootPath : ''; ?>index.php"
                                    class="capitalize hover:text-[#8c76a8] transition-transform duration-300 hover:scale-105 inline-block">
                                    <?php echo isset($t['menu_home']) ? $t['menu_home'] : 'Home'; ?>
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo isset($rootPath) ? $rootPath : ''; ?>pagina_sobrenos.php"
                                    class="capitalize hover:text-[#8c76a8] transition-transform duration-300 hover:scale-105 inline-block">
                                    <?php echo isset($t['menu_about']) ? $t['menu_about'] : 'Sobre Nós'; ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="flex flex-col items-center lg:items-end justify-start">
                    <div class="flex flex-col items-center"> <span
                            class="text-[#fbeaff] block text-xl font-bold mb-5 text-center">
                            <?php echo isset($t['footer_follow']) ? $t['footer_follow'] : 'Siga-Nos'; ?>
                        </span>
                        <div class="flex justify-center gap-4">
                            <a href="https://www.facebook.com/?locale=pt_PT" target="_blank"
                                class="w-10 h-10 rounded-full bg-[#5d3e8f] text-[#fbeaff] flex items-center justify-center transition-transform duration-300 hover:scale-110 hover:bg-[#4a327a]">
                                <i class="fab fa-facebook-f"></i>
                            </a>

                            <a href="https://x.com/" target="_blank"
                                class="w-10 h-10 rounded-full bg-[#5d3e8f] text-[#fbeaff] flex items-center justify-center transition-transform duration-300 hover:scale-110 hover:bg-[#4a327a]">
                                <i class="fab fa-twitter"></i>
                            </a>

                            <a href="https://www.instagram.com/" target="_blank"
                                class="w-10 h-10 rounded-full bg-[#5d3e8f] text-[#fbeaff] flex items-center justify-center transition-transform duration-300 hover:scale-110 hover:bg-[#4a327a]">
                                <i class="fab fa-instagram"></i>
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="bg-[#151414] py-6 border-t border-[#2a2a2a]">
        <div class="max-w-[1200px] mx-auto px-4">
            <div class="flex flex-col-reverse lg:flex-row justify-between items-center gap-6 text-center lg:text-left">

                <div class="text-sm text-gray-400">
                    <p class="m-0">
                        Copyright &copy; 2025,
                        <?php echo isset($t['footer_rights']) ? $t['footer_rights'] : 'Todos os direitos reservados'; ?>
                        <a href="<?php echo isset($rootPath) ? $rootPath : ''; ?>index.php"
                            class="text-[#8c76a8] hover:underline font-bold ml-1">SAPOSalas</a>
                    </p>
                </div>

                <div>
                    <ul
                        class="flex flex-wrap justify-center lg:justify-end gap-4 lg:gap-6 m-0 p-0 list-none text-sm font-medium">
                        <li>
                            <a href="<?php echo isset($rootPath) ? $rootPath : '../../'; ?>termos_de_utilizador.php"
                                class="hover:text-[#8c76a8] transition-colors duration-300">
                                <?php echo isset($t['footer_terms']) ? $t['footer_terms'] : 'Termos e Condições'; ?>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo isset($rootPath) ? $rootPath : '../../'; ?>politica_de_privacidade.php"
                                class="hover:text-[#8c76a8] transition-colors duration-300">
                                <?php echo isset($t['footer_privacy']) ? $t['footer_privacy'] : 'Privacidade'; ?>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo isset($rootPath) ? $rootPath : '../../'; ?>politica_de_cookies.php"
                                class="hover:text-[#8c76a8] transition-colors duration-300">
                                <?php echo isset($t['footer_cookies']) ? $t['footer_cookies'] : 'Política de Cookies'; ?>
                            </a>
                        </li>
                    </ul>
                </div>

            </div>
        </div>
    </div>
</footer>

<script>
    if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }

    window.addEventListener('storage', function (event) {
        if (event.key === 'logout-event') {
            window.location.href = 'login.php';
        }
    });
</script>