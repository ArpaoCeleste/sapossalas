<?php

$rootPath = './';

$t = [];
if (file_exists($rootPath . 'includes/lang.php')) {
    require_once $rootPath . 'includes/lang.php';
}
?>
<!DOCTYPE html>
<html lang="pt-PT">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="<?php echo $t['meta_description_about'] ?? 'Sobre a equipa SAPOSalas'; ?>" />
    <title><?php echo $t['page_title_about'] ?? 'Sobre Nós'; ?> - SAPOSalas</title>

    <link rel="icon" type="image/png" href="<?php echo $rootPath; ?>imagens/pngsapo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icon-css@3.5.0/css/flag-icon.min.css" />
<script src="https://cdn.jsdelivr.net/npm/three@0.155.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tsparticles@3/tsparticles.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/particles.js"></script>

<style>
    body {
        font-family: 'Poppins', sans-serif;
    }

    .imgsobre:hover,
    .imgsobrelourenco:hover {
        transform: scale(1.08);
    }

    body.dark .text-[#555] {
        color: #ccc;
    }

    body.dark .text-gray-700 {
        color: #d1d5db;
    }

    body.dark .text-gray-800 {
        color: #f3f4f6;
    }

    body.dark .bg-white {
        background-color: #1f2937;
    }

    .imgsobre {
        --s: min(50vw, 360px);

        width: var(--s);
        aspect-ratio: 1;
        object-fit: contain;
        object-position: top;
        padding: calc(var(--s)/4) calc(var(--s)/8) 0;
        box-sizing: border-box;

        background: conic-gradient(from 135deg at 50% 15%, #5d3e8f, #8c76a8 25%, #fbeaff 0);
        clip-path: polygon(-50% 0, 150% 0, 50% 100%);
        transition: .5s;
        cursor: pointer;
        border-radius: 9999px;
    }


    .imgsobre:hover {
        padding: 0;
    }


    .dark .imgsobre {

        background: conic-gradient(from 135deg at 50% 15%, #d8b4fe, #5d3e8f 25%, #1f2937 0);
    }




    #efeitosCanvas {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 10;
    }


    @keyframes dash {
        from {
            stroke-dasharray: 0, 500;
        }

        to {
            stroke-dasharray: 500, 0;
        }
    }

    .animate-dash {
        stroke-dasharray: 500;
        stroke-dashoffset: 500;
        animation: dash 3s ease-in-out forwards;
    }


    @keyframes fadeInLeft {
        from {
            opacity: 0;
            transform: translateX(-40px);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-fadeInLeft {
        animation: fadeInLeft 1s ease-out forwards;
    }

    .animate-fadeInUp {
        animation: fadeInUp 1s ease-out forwards;
    }

    .delay-200 {
        animation-delay: 0.2s;
    }

    .delay-300 {
        animation-delay: 0.3s;
    }

    .delay-500 {
        animation-delay: 0.5s;
    }

    .delay-700 {
        animation-delay: 0.7s;
    }
</style>

</head>

<body class=" bg-gray-100 dark:bg-gray-900 transition-colors duration-300">

    <?php include $rootPath . 'includes/navbar.php'; ?>

    <main>

        <h2 class="text-4xl text-center md:text-5xl font-bold text-[#5d3e8f] dark:text-[#d8b4fe] mb-4">
            <?php echo $t['team_members_title'] ?? 'Membros da Equipa'; ?>
        </h2>

        <div class="flex flex-wrap justify-center gap-6 mt-8 ">
            <img class="imgsobre dark:bg-gray-800" src="imagens/default.png" alt="Membro 1"
                onclick="mostrarSobre('member1')">
            <img class="imgsobre dark:bg-gray-800" src="imagens/default.png" alt="Membro 2"
                onclick="mostrarSobre('member2')">
        </div>

        <div id="sobreSecao" class="relative hidden mt-16 p-10 mx-auto rounded-xl bg-white dark:bg-gray-900  transition-colors duration-300
             flex flex-col md:flex-row items-center md:items-center justify-center gap-6 md:gap-10 w-full ">

            <div id="efeitosContainer"
                class="absolute inset-0 w-full h-full pointer-events-none z-10 flex items-center justify-center overflow-hidden rounded-xl">
            </div>

            <div class="relative z-20 w-full md:w-auto flex justify-center shrink-0">
                <img id="sobreImagem" src="" alt="<?php echo $t['team_member_image_alt'] ?? 'Imagem do Membro'; ?>"
                    class="w-[280px] h-[280px] md:w-[320px] md:h-[320px] rounded-2xl shadow-xl border-4 border-[#5d3e8f] dark:border-[#d8b4fe] transition-transform ease-in-out duration-500 hover:scale-105 object-cover" />
            </div>

            <div class="relative z-20 text-center md:text-left text-gray-800 dark:text-gray-200 pt-4 md:pt-0">

                <h4 class="uppercase tracking-[0.25em] text-xs text-gray-500 dark:text-gray-400 mb-3">
                    <?php echo $t['team_about_me'] ?? 'Sobre o Membro'; ?>
                </h4>

                <h1 class="text-4xl md:text-5xl font-extrabold mb-6 leading-tight">
                    <span id="sobreNome" class="text-[#5d3e8f] dark:text-[#d8b4fe] drop-shadow-sm">...</span>
                </h1>

                <p id="sobreTexto"
                    class="text-lg md:text-xl leading-relaxed text-gray-700 dark:text-gray-300 mb-8 max-w-[600px] break-words">
                    ...
                </p>

                <h4 class="uppercase font-extrabold text-[#5d3e8f] dark:text-[#d8b4fe] text-lg mb-3">
                    <?php echo $t['team_role_title'] ?? 'Funções'; ?>
                </h4>

                <p id="sobrefuncoes" class="text-lg md:text-xl leading-relaxed text-gray-700 dark:text-gray-300 mb-8">
                    ...
                </p>
            </div>
        </div>


        <section class="py-16 px-6 md:px-20 text-center">
            <h2 class="text-4xl md:text-5xl font-bold text-[#5d3e8f] dark:text-[#d8b4fe] mb-4">
                <?php echo $t['about_saposalas_title'] ?? 'Sobre o SAPOSalas'; ?>
            </h2>
            <p class="text-lg text-[#555] dark:text-gray-300 max-w-4xl mx-auto mb-12">
                <?php echo $t['saposalas_mission'] ?? 'Somos uma plataforma dedicada a otimizar a gestão e reserva de salas e recursos da instituição, garantindo que o espaço certo está disponível na hora certa. O nosso objetivo é simplificar o processo de agendamento, garantindo transparência, disponibilidade e eficiência para todos os utilizadores.'; ?>
            </p>

            <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
                    <h3 class="text-3xl font-bold text-[#5d3e8f] dark:text-[#d8b4fe]">+3000</h3>
                    <p class="text-[#555] dark:text-gray-400 mt-2">
                        <?php echo $t['stat_reservas'] ?? 'Reservas Efetuadas'; ?>
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
                    <h3 class="text-3xl font-bold text-[#5d3e8f] dark:text-[#d8b4fe]">99.9%</h3>
                    <p class="text-[#555] dark:text-gray-400 mt-2">
                        <?php echo $t['stat_uptime'] ?? 'Tempo de Atividade (Uptime)'; ?>
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
                    <h3 class="text-3xl font-bold text-[#5d3e8f] dark:text-[#d8b4fe]">+50</h3>
                    <p class="text-[#555] dark:text-gray-400 mt-2"><?php echo $t['stat_rooms'] ?? 'Salas Geridas'; ?>
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
                    <h3 class="text-3xl font-bold text-[#5d3e8f] dark:text-[#d8b4fe]">
                        <1 Minuto</h3>
                            <p class="text-[#555] dark:text-gray-400 mt-2">
                                <?php echo $t['stat_confirm'] ?? 'Confirmação de Reserva'; ?>
                            </p>
                </div>
            </div>
        </section>

        <section class="py-10 px-6 md:px-20 grid grid-cols-1 md:grid-cols-2 place-items-center gap-6">

            <div class="text-center md:text-left max-w-xl">
                <h2 class="text-4xl md:text-5xl font-bold text-[#5d3e8f] dark:text-[#d8b4fe] mb-4">
                    <?php echo $t['chart_title'] ?? 'Evolução das Reservas'; ?>
                </h2>

                <p class="text-lg text-[#555] dark:text-gray-300 mb-2">
                    <?php echo $t['chart_text_1'] ?? 'Desde o início do projeto, o número de reservas cresceu de forma consistente, mostrando a rápida adoção por parte dos utilizadores.'; ?>
                </p>

                <p class="text-lg text-[#555] dark:text-gray-300">
                    <?php echo $t['chart_text_2'] ?? 'A nossa equipa evoluiu em conhecimento técnico, criatividade e capacidade de resposta — sempre com foco na experiência do utilizador.'; ?>
                </p>
            </div>

            <div
                class="w-full max-w-2xl bg-white dark:bg-gray-800 p-4 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <canvas id="reservationsChart" class="w-full h-[350px] md:h-[450px]"></canvas>
            </div>
        </section>

        <script>

            const ctx = document.getElementById('reservationsChart').getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, '#5d3e8f');
            gradient.addColorStop(1, '#fbeaff');

            new Chart(ctx, {
                type: 'line',
                data: {

                    labels: ['2022', '2023', '2024', '2025'],
                    datasets: [{

                        label: '<?php echo $t['chart_label_reservations'] ?? 'Reservas Efetuadas'; ?>',

                        data: [50, 250, 900, 3000],
                        borderColor: gradient,
                        backgroundColor: 'rgba(93,62,143,0.15)',
                        tension: 0.4,
                        pointBackgroundColor: '#5d3e8f',
                        pointBorderWidth: 2,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        pointRadius: 7,
                        pointHoverRadius: 9,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    animation: {
                        duration: 1500,
                        easing: 'easeOutQuart'
                    },
                    plugins: {
                        legend: {
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle',
                                color: '#5d3e8f',
                                font: { size: 14, weight: 'bold' }
                            }
                        },
                        tooltip: {
                            backgroundColor: '#5d3e8f',
                            titleColor: '#fff',
                            bodyColor: '#fbeaff',
                            padding: 12,
                            borderWidth: 1,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            cornerRadius: 12
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: '#555', font: { weight: 'bold' } },
                            grid: { color: 'rgba(93,62,143,0.1)' }
                        },
                        y: {
                            beginAtZero: true,

                            ticks: { color: '#555', stepSize: 500 },
                            grid: { color: 'rgba(93,62,143,0.1)' }
                        }
                    }
                }
            });
        </script>

        <section class="py-12 px-6 md:px-20 text-center">
            <h2 class="text-4xl md:text-5xl font-bold text-[#5d3e8f] dark:text-[#d8b4fe] mb-6">
                <?php echo $t['location_title'] ?? 'Localização'; ?>
            </h2>

            <div
                class="max-w-4xl mx-auto rounded-xl overflow-hidden shadow-lg border border-[#e0c9f7] dark:border-[#5d3e8f]/50">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d11025140.247296128!2d-24.1095302147224!3d35.884614676207256!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xb32242dbf4226d5%3A0x2ab84b091c4ef041!2sPortugal!5e1!3m2!1spt-PT!2spt!4v1766089778922!5m2!1spt-PT!2spt" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>

            <p class="text-[#555] dark:text-gray-300 mt-6 max-w-2xl mx-auto">
                <?php echo $t['location_text'] ?? 'Estamos localizados num ponto central pronto para receber parceiros e desenvolvermos o nosso projeto.'; ?>
            </p>
        </section>

        <section class="relative flex flex-col md:flex-row items-center justify-center 
            min-h-[60vh] md:min-h-[70vh]
            bg-gradient-to-b md:bg-gradient-to-r 
            from-[#5d3e8f] via-[#8c76a8] to-[#fbeaff]
            overflow-hidden px-6 py-10">

            <div class="absolute md:left-0 top-0 w-full md:w-[48%] h-[40vh] md:h-full
                        bg-[url('imagens/pngsapo.png')] bg-contain bg-center
                        md:[mask-image:linear-gradient(to_right,black,transparent)]
                        md:[-webkit-mask-image:linear-gradient(to_right,black,transparent)]
                        z-10">
            </div>

            <div class="relative z-20 mt-[45vh] md:mt-0 max-w-2xl md:ml-auto md:mr-10">
                <h4 class="uppercase text-[#444] tracking-[2px] mb-3 text-sm md:text-base">
                    <?php echo $t['reviews_subtitle'] ?? 'Opiniões dos Utilizadores'; ?>
                </h4>

                <h1 class="text-3xl md:text-5xl font-extrabold leading-tight mb-6">
                    <?php echo $t['reviews_main_text_part1'] ?? 'O que os utilizadores'; ?> <span
                        class="text-[#5d3e8f]"><?php echo $t['reviews_main_text_part2'] ?? 'dizem'; ?></span>
                </h1>

                <p class="text-lg text-[#444] leading-relaxed mb-10">
                    <?php echo $t['reviews_desc'] ?? 'Feedback real de quem confia no SAPOSalas para reservar o seu espaço de trabalho/estudo.'; ?>
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <blockquote
                        class="bg-white/90 dark:bg-gray-700/80 backdrop-blur p-6 rounded-xl shadow hover:shadow-xl transition hover:-translate-y-1">
                        <p class="text-[#5d3e8f] dark:text-[#d8b4fe] font-semibold">
                            "<?php echo $t['review_vilarinho'] ?? 'A plataforma é intuitiva e rápida. Finalmente não marco a sala que já está ocupada!'; ?>"
                        </p>
                        <footer class="text-sm text-[#555] dark:text-gray-400 mt-2">—
                            <?php echo $t['review_vilarinho_author'] ?? 'Professor Universitário'; ?>
                        </footer>
                    </blockquote>

                    <blockquote
                        class="bg-white/90 dark:bg-gray-700/80 backdrop-blur p-6 rounded-xl shadow hover:shadow-xl transition hover:-translate-y-1">
                        <p class="text-[#5d3e8f] dark:text-[#d8b4fe] font-semibold">
                            "<?php echo $t['review_nelsu'] ?? 'Consigo ver a ocupação em tempo real. A funcionalidade de cancelamento é essencial.'; ?>"
                        </p>
                        <footer class="text-sm text-[#555] dark:text-gray-400 mt-2">—
                            <?php echo $t['review_nelsu_author'] ?? 'Aluno de Engenharia'; ?>
                        </footer>
                    </blockquote>

                    <blockquote
                        class="bg-white/90 dark:bg-gray-700/80 backdrop-blur p-6 rounded-xl shadow hover:shadow-xl transition hover:-translate-y-1 md:col-span-2">
                        <p class="text-[#5d3e8f] dark:text-[#d8b4fe] font-semibold">
                            "<?php echo $t['review_civica'] ?? 'Excelente suporte e explicações claras. A integração com o calendário funciona perfeitamente.'; ?>"
                        </p>
                        <footer class="text-sm text-[#555] dark:text-gray-400 mt-2">—
                            <?php echo $t['review_civica_author'] ?? 'Equipa de Gestão'; ?>
                        </footer>
                    </blockquote>
                </div>
            </div>
        </section>


    </main>

    <?php include $rootPath . 'includes/footer.php'; ?>
    <?php include $rootPath . 'includes/chat_support.php'; ?>

    <script>
        let efeitoCanvas = null;
        let confettiInstance = null;
        let membroAtivo = null;
        let canvasEfeito = null;
        let ctxEfeito = null;

        function iniciarCanvasSecao() {
            const container = document.getElementById("efeitosContainer");
            container.innerHTML = "";


            efeitoCanvas = document.createElement("canvas");
            efeitoCanvas.width = container.clientWidth;
            efeitoCanvas.height = container.clientHeight;
            efeitoCanvas.style.position = "absolute";
            efeitoCanvas.style.inset = "0";
            efeitoCanvas.style.width = "100%";
            efeitoCanvas.style.height = "100%";

            container.appendChild(efeitoCanvas);

            if (typeof confetti !== 'undefined' && confetti.create) {
                confettiInstance = confetti.create(efeitoCanvas, {
                    resize: true,
                    useWorker: true
                });
            } else {
                console.error("Confetti instance not available.");
            }


            canvasEfeito = document.createElement("canvas");
            canvasEfeito.width = container.clientWidth;
            canvasEfeito.height = container.clientHeight;
            canvasEfeito.style.position = "absolute";
            canvasEfeito.style.inset = "0";
            canvasEfeito.style.width = "100%";
            canvasEfeito.style.height = "100%";
            canvasEfeito.style.pointerEvents = "none";

            container.appendChild(canvasEfeito);

            ctxEfeito = canvasEfeito.getContext("2d");
        }

        const dadosSobre = {
            member1: {
                nome: "<?php echo $t['team_member1_name'] ?? 'Membro 1'; ?>",
                imagem: "<?php echo $rootPath; ?>imagens/default.png",
                texto: "<?php echo $t['team_member1_desc'] ?? 'Descrição do membro da equipa. Responsável e dedicado.'; ?>",
                funcoes: "<?php echo $t['team_member1_role'] ?? 'Função na equipa'; ?>"
            },
            member2: {
                nome: "<?php echo $t['team_member2_name'] ?? 'Membro 2'; ?>",
                imagem: "<?php echo $rootPath; ?>imagens/default.png",
                texto: "<?php echo $t['team_member2_desc'] ?? 'Descrição do membro da equipa. Especialista e criativo.'; ?>",
                funcoes: "<?php echo $t['team_member2_role'] ?? 'Função na equipa'; ?>"
            },

        };


        (function precarregarImagens() {
            const valores = Object.values(dadosSobre);
            valores.forEach(membro => {
                if (membro.imagem) {
                    const img = new Image();
                    img.src = membro.imagem;
                }
            });
        })();



        function snowEffect() {
            const duration = 4000;
            const end = Date.now() + duration;

            (function snow() {
                if (confettiInstance) confettiInstance({
                    particleCount: 5,
                    startVelocity: 0,
                    gravity: 0.2,
                    ticks: 150,
                    origin: { x: Math.random(), y: 0 },
                    colors: ["#5d3e8f", "#d8b4fe"],
                    shapes: ["circle"]
                });

                if (Date.now() < end) requestAnimationFrame(snow);
            })();
        }

        function fireworksEffect() {
            const duration = 4000;
            const end = Date.now() + duration;

            (function frame() {
                if (confettiInstance) {
                    confettiInstance({
                        particleCount: 5,
                        angle: 60,
                        spread: 55,
                        startVelocity: 40,
                        origin: { x: 0, y: 0.8 },
                        colors: ["#5d3e8f", "#8c76a8", "#fbeaff"]
                    });

                    confettiInstance({
                        particleCount: 5,
                        angle: 120,
                        spread: 55,
                        startVelocity: 40,
                        origin: { x: 1, y: 0.8 },
                        colors: ["#5d3e8f", "#8c76a8", "#fbeaff"]
                    });
                }

                if (Date.now() < end) requestAnimationFrame(frame);
            })();
        }

        function criarCanvasExtra() {
            const container = document.getElementById("efeitosContainer");
            const canvas = document.createElement("canvas");

            canvas.width = container.clientWidth;
            canvas.height = container.clientHeight;
            canvas.style.position = "absolute";
            canvas.style.inset = "0";
            canvas.style.pointerEvents = "none";

            container.appendChild(canvas);
            return canvas;
        }

        function efeitoSparkles() {
            const canvas = criarCanvasExtra();
            const ctx = canvas.getContext("2d");

            const DURATION = 6000;
            const FADE_TIME = 700;
            const startTime = performance.now();

            const sparks = Array.from({ length: 120 }).map(() => ({
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height,
                size: Math.random() * 3 + 1,
                alpha: Math.random()
            }));

            function anim() {
                const now = performance.now();
                const elapsed = now - startTime;
                const remaining = DURATION - elapsed;

                if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);

                let fadeMultiplier = 1;

                if (remaining < FADE_TIME) {
                    fadeMultiplier = remaining / FADE_TIME;
                }

                sparks.forEach(s => {
                    s.alpha += (Math.random() - .5) * 0.06;
                    s.alpha = Math.max(0, Math.min(1, s.alpha));

                    if (ctx) {
                        ctx.fillStyle = `rgba(180,120,255,${s.alpha * fadeMultiplier})`;
                        ctx.beginPath();
                        ctx.arc(s.x, s.y, s.size, 0, Math.PI * 2);
                        ctx.fill();
                    }
                });

                if (elapsed < DURATION) {
                    requestAnimationFrame(anim);
                } else {
                    canvas.remove();
                }
            }

            anim();
        }

        function fogos() {
            const canvas = criarCanvasExtra();
            const ctx = canvas.getContext("2d");

            const DURACAO = 6500;
            const INTERVALO = 1000;
            const COLORS = ["#5d3e8f", "#8c76a8", "#bda4dd", "#d8c9f1"];

            let foguetes = [];

            function criarFoguete() {
                const areaX = Math.random() * canvas.width;
                const areaY = canvas.height - (Math.random() * canvas.height * 0.6);

                return {
                    x: Math.random() * canvas.width,
                    y: canvas.height + 20,
                    vx: (Math.random() - 0.5) * 1.5,
                    vy: -7 - Math.random() * 3,
                    explodeX: areaX,
                    explodeY: areaY,
                    exploded: false,
                    trail: [],
                    particles: [],
                    color: COLORS[Math.floor(Math.random() * COLORS.length)]
                };
            }

            function hexToRgba(hex, alpha) {
                const r = parseInt(hex.substr(1, 2), 16);
                const g = parseInt(hex.substr(3, 2), 16);
                const b = parseInt(hex.substr(5, 2), 16);
                return `rgba(${r}, ${g}, ${b}, ${alpha})`;
            }

            function anim() {
                if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);

                foguetes.forEach((f, index) => {
                    if (!f.exploded) {
                        f.x += f.vx;
                        f.y += f.vy;
                        f.vy += 0.10;
                        f.trail.push({ x: f.x, y: f.y });
                        if (f.trail.length > 18) f.trail.shift();

                        f.trail.forEach((t, i) => {
                            const fade = i / f.trail.length;
                            if (ctx) {
                                ctx.fillStyle = hexToRgba(f.color, 0.9 - fade);
                                ctx.beginPath();
                                ctx.arc(t.x, t.y, 3 - fade, 0, Math.PI * 2);
                                ctx.fill();
                            }
                        });

                        if (ctx) {
                            ctx.fillStyle = hexToRgba(f.color, 1);
                            ctx.beginPath();
                            ctx.arc(f.x, f.y, 5, 0, Math.PI * 2);
                            ctx.fill();
                        }

                        if (f.y <= f.explodeY || f.vy > -1.2) {
                            f.exploded = true;

                            const count = 120 + Math.random() * 80;
                            for (let i = 0; i < count; i++) {
                                const angle = i * (Math.PI * 2 / count);
                                const speed = 2 + Math.random() * 3;

                                f.particles.push({
                                    x: f.x,
                                    y: f.y,
                                    vx: Math.cos(angle) * speed,
                                    vy: Math.sin(angle) * speed,
                                    life: 1,
                                    size: 2 + Math.random() * 2,
                                    color: f.color
                                });
                            }
                        }

                    } else {

                        f.particles.forEach(p => {
                            p.x += p.vx;
                            p.y += p.vy;
                            p.vy += 0.025;
                            p.life -= 0.015;
                        });


                        f.particles.forEach(p => {
                            if (ctx) {
                                ctx.fillStyle = hexToRgba(p.color, Math.max(0, p.life));
                                ctx.beginPath();
                                ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
                                ctx.fill();
                            }
                        });


                        f.particles = f.particles.filter(p => p.life > 0.02);
                    }
                });


                foguetes = foguetes.filter(f => !f.exploded || f.particles.length > 0);

                requestAnimationFrame(anim);
            }

            anim();


            const lancador = setInterval(() => {
                foguetes.push(criarFoguete());
            }, INTERVALO);


            setTimeout(() => {
                clearInterval(lancador);
                setTimeout(() => canvas.remove(), 5000);
            }, DURACAO);
        }


        let particlesSnow = [];
        const snowColors = ["#5d3e8f", "#8c76a8", "#fbeaff"];

        function iniciarSnow(qtd = 80) {
            if (!canvasEfeito) return;

            particlesSnow = [];

            const W = canvasEfeito.width;
            const H = canvasEfeito.height;

            for (let i = 0; i < qtd; i++) {
                particlesSnow.push({
                    x: Math.random() * W,
                    y: Math.random() * H,
                    speed: 0.5 + Math.random() * 1.2,
                    size: 1 + Math.random() * 3,
                    color: snowColors[Math.floor(Math.random() * snowColors.length)]
                });
            }
        }

        function atualizarSnow() {
            if (!canvasEfeito) return;
            const W = canvasEfeito.width;
            const H = canvasEfeito.height;

            for (let p of particlesSnow) {
                p.y += p.speed;

                if (p.y > H) {
                    p.y = -5;
                    p.x = Math.random() * W;
                }
            }
        }

        function desenharSnow() {
            if (!canvasEfeito || !ctxEfeito) return;

            ctxEfeito.clearRect(0, 0, canvasEfeito.width, canvasEfeito.height);

            for (let p of particlesSnow) {
                ctxEfeito.fillStyle = p.color;
                ctxEfeito.beginPath();
                ctxEfeito.arc(p.x, p.y, p.size, 0, Math.PI * 2);
                ctxEfeito.fill();
            }
        }

        let neveAtiva = false;

        function neveEfeito6000() {
            if (neveAtiva || !canvasEfeito) return;
            neveAtiva = true;

            const duration = 6400;
            const end = Date.now() + duration;

            iniciarSnow(200);

            function loop() {
                atualizarSnow();
                desenharSnow();

                if (Date.now() < end) {
                    requestAnimationFrame(loop);
                } else {
                    particlesSnow = [];
                    if (ctxEfeito) ctxEfeito.clearRect(0, 0, canvasEfeito.width, canvasEfeito.height);
                    neveAtiva = false;
                }
            }

            loop();
        }


        function mostrarSobre(id) {
            const secao = document.getElementById("sobreSecao");
            const img = document.getElementById("sobreImagem");
            const nome = document.getElementById("sobreNome");
            const texto = document.getElementById("sobreTexto");
            const funcoes = document.getElementById("sobrefuncoes");


            if (membroAtivo === id) {
                secao.classList.add("hidden");
                membroAtivo = null;
                return;
            }

            membroAtivo = id;

            const dados = dadosSobre[id];
            if (!dados) return;

            img.src = dados.imagem;
            nome.textContent = dados.nome;
            texto.innerHTML = dados.texto;
            funcoes.innerHTML = dados.funcoes;

            secao.classList.remove("hidden");

            requestAnimationFrame(() => {

                iniciarCanvasSecao();


                if (confettiInstance) {
                    fireworksEffect();
                    snowEffect();
                }


                if (ctxEfeito) {
                    efeitoSparkles();
                    fogos();
                    neveEfeito6000();
                }
            });

            secao.scrollIntoView({ behavior: "smooth" });
        }
    </script>
</body>

</html>