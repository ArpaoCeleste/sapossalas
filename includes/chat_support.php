<?php
require_once __DIR__ . '/../backend/config.php';

$currentUserAvatar = '';
if (isset($_SESSION['user_id'])) {
    $stmt_chat_img = $conn->prepare("SELECT imagem_perfil FROM utilizadores WHERE id = ?");
    $stmt_chat_img->bind_param("i", $_SESSION['user_id']);
    $stmt_chat_img->execute();
    $res_chat_img = $stmt_chat_img->get_result();
    if ($res_chat_img->num_rows > 0) {
        $row_img = $res_chat_img->fetch_assoc();
        if (!empty($row_img['imagem_perfil'])) {
            $currentUserAvatar = htmlspecialchars($row_img['imagem_perfil']);
        }
    }
    $stmt_chat_img->close();
}
?>

<div id="chat-widget" class="fixed bottom-5 right-5 z-[9999] font-['Poppins']">

    <button id="chat-toggle-btn"
        class="w-14 h-14 rounded-full bg-gradient-to-br from-[#5d3e8f] to-[#4a327a] dark:from-[#d8b4fe] dark:to-[#b794f4] text-white dark:text-[#151414] shadow-lg hover:shadow-xl hover:scale-110 transition-all duration-300 flex items-center justify-center group relative">
        <i class="fas fa-comments text-2xl"></i>
        <span id="chat-notification"
            class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold w-5 h-5 flex items-center justify-center rounded-full border-2 border-white dark:border-gray-800 hidden animate-bounce">1</span>
    </button>

    <div id="chat-window"
        class="fixed bottom-24 right-5 w-96 max-w-[calc(100vw-40px)] h-[550px] max-h-[calc(100vh-120px)] bg-white dark:bg-gray-800 rounded-2xl shadow-2xl flex-col hidden overflow-hidden border border-gray-200 dark:border-gray-700 transition-all duration-300 origin-bottom-right">

        <div
            class="bg-gradient-to-r from-[#5d3e8f] to-[#4a327a] p-4 flex justify-between items-center text-white shadow-md">
            <div class="flex items-center gap-3">
                <div
                    class="w-10 h-10 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center text-white">
                    <i class="fas fa-robot text-lg"></i>
                </div>
                <div>
                    <h4 class="font-bold text-sm leading-tight">
                        <?php echo $t['chat_title'] ?? 'Assistente SAPOSalas'; ?></h4>
                    <span class="flex items-center gap-1.5 text-xs opacity-90">
                        <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
                        <?php echo $t['chat_online'] ?? 'Online'; ?>
                    </span>
                </div>
            </div>
            <div class="flex gap-2">
                <button id="chat-minimize-btn"
                    class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors">
                    <i class="fas fa-minus text-xs"></i>
                </button>
                <button id="chat-close-btn"
                    class="w-8 h-8 rounded-full bg-white/10 hover:bg-red-500/80 flex items-center justify-center transition-colors">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
        </div>

        <div id="chat-body" class="flex-1 overflow-y-auto p-4 bg-gray-50 dark:bg-gray-900 scroll-smooth space-y-4">
            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                <div
                    class="w-16 h-16 rounded-full bg-[#5d3e8f]/10 dark:bg-[#d8b4fe]/10 text-[#5d3e8f] dark:text-[#d8b4fe] flex items-center justify-center text-3xl mx-auto mb-3">
                    <i class="fas fa-robot"></i>
                </div>
                <h3 class="font-bold text-gray-800 dark:text-gray-200 mb-1">
                    <?php echo $t['chat_welcome_title'] ?? 'Olá! 👋'; ?></h3>
                <p class="text-sm">
                    <?php echo $t['chat_welcome_text'] ?? 'Bem-vindo ao SAPOSalas. Como posso ajudar?'; ?></p>
            </div>
        </div>

        <div class="p-4 bg-white dark:bg-gray-800 border-t border-gray-100 dark:border-gray-700">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="flex items-center gap-2">
                    <input type="text" id="chat-input"
                        class="flex-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-white border-0 rounded-full px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#5d3e8f] dark:focus:ring-[#d8b4fe] placeholder-gray-400 dark:placeholder-gray-500 transition-all"
                        placeholder="<?php echo $t['chat_placeholder'] ?? 'Digite a sua mensagem...'; ?>"
                        autocomplete="off">

                    <button id="chat-send-btn"
                        class="flex-shrink-0 w-10 h-10 rounded-full bg-[#5d3e8f] hover:bg-[#4a327a] dark:bg-[#d8b4fe] dark:hover:bg-[#c084fc] text-white dark:text-[#151414] flex items-center justify-center shadow-sm transition-transform hover:scale-105 active:scale-95 ">
                        <i class="fas fa-paper-plane text-sm"></i>
                    </button>
                </div>
                <div id="typing-indicator"
                    class="hidden items-center gap-1.5 mt-2 ml-4 text-xs text-gray-400 dark:text-gray-500">
                    <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce"></span>
                    <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce delay-75"></span>
                    <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce delay-150"></span>
                    <span class="ml-1"><?php echo $t['chat_typing'] ?? 'A escrever...'; ?></span>
                </div>
            <?php else: ?>
                <div class="text-center p-2">
                    <i class="fas fa-lock text-[#5d3e8f] dark:text-[#d8b4fe] text-2xl mb-2"></i>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                        <?php echo $t['chat_login_req'] ?? 'Faça login para usar o chat.'; ?></p>
                    <a href="login.php"
                        class="inline-flex items-center px-4 py-2 bg-[#5d3e8f] text-white rounded-full text-xs font-bold hover:bg-[#4a327a] transition-colors">
                        <?php echo $t['btn_login'] ?? 'Entrar'; ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const chatToggleBtn = document.getElementById('chat-toggle-btn');
        const chatWindow = document.getElementById('chat-window');
        const chatCloseBtn = document.getElementById('chat-close-btn');
        const chatMinimizeBtn = document.getElementById('chat-minimize-btn');
        const chatInput = document.getElementById('chat-input');
        const chatSendBtn = document.getElementById('chat-send-btn');
        const chatBody = document.getElementById('chat-body');
        const typingIndicator = document.getElementById('typing-indicator');
        const chatNotification = document.getElementById('chat-notification');

        const userAvatarUrl = "<?php echo $currentUserAvatar; ?>";

        function toggleChat() {
            chatWindow.classList.toggle('hidden');
            chatWindow.classList.toggle('flex');

            if (!chatWindow.classList.contains('hidden')) {
                setTimeout(() => chatInput?.focus(), 100);
                chatNotification.style.display = 'none';
            }
        }

        chatToggleBtn?.addEventListener('click', toggleChat);
        chatCloseBtn?.addEventListener('click', toggleChat);
        chatMinimizeBtn?.addEventListener('click', toggleChat);

        function sendMessage() {
            const message = chatInput?.value.trim();
            if (!message) return;

            addMessage(message, 'user');
            if (chatInput) chatInput.value = '';

            if (typingIndicator) typingIndicator.style.display = 'flex';
            chatBody.scrollTop = chatBody.scrollHeight;

            setTimeout(() => {
                getBotResponse(message);
                if (typingIndicator) typingIndicator.style.display = 'none';
            }, 800 + Math.random() * 800);
        }

        chatSendBtn?.addEventListener('click', sendMessage);
        chatInput?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendMessage();
        });

        function addMessage(text, sender) {
            const messageDiv = document.createElement('div');
            const isUser = sender === 'user';

            messageDiv.className = `flex w-full mb-4 ${isUser ? 'justify-end' : 'justify-start'}`;

            let avatar = '';

            if (isUser) {
                if (userAvatarUrl && userAvatarUrl.trim() !== '') {
                    avatar = `<img src="${userAvatarUrl}" class="w-8 h-8 rounded-full object-cover flex-shrink-0 ml-2 shadow-sm border border-gray-200" alt="User">`;
                } else {
                    avatar = `<div class="w-8 h-8 rounded-full bg-gradient-to-br from-[#667eea] to-[#764ba2] flex items-center justify-center text-white text-xs flex-shrink-0 ml-2 shadow-sm"><i class="fas fa-user"></i></div>`;
                }
            } else {
                avatar = `<div class="w-8 h-8 rounded-full bg-[#5d3e8f] flex items-center justify-center text-white text-xs flex-shrink-0 mr-2 shadow-sm"><i class="fas fa-robot"></i></div>`;
            }

            const bubbleStyle = isUser
                ? 'bg-[#5d3e8f] text-white rounded-l-2xl rounded-tr-2xl'
                : 'bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-600 rounded-r-2xl rounded-tl-2xl';

            const content = `
            <div class="${bubbleStyle} px-4 py-2.5 shadow-sm max-w-[85%] text-sm leading-relaxed">
                ${text}
            </div>
        `;

            messageDiv.innerHTML = isUser ? content + avatar : avatar + content;
            chatBody.appendChild(messageDiv);
            chatBody.scrollTop = chatBody.scrollHeight;
        }

        function getBotResponse(userMessage) {
            const msg = userMessage.toLowerCase();
            let response = '';

            if (msg.includes('olá') || msg.includes('oi') || msg.includes('boas') || msg.includes('viva')) {
                response = '<?php echo $t['chat_msg_welcome'] ?? "Olá! 👋 Bem-vindo ao SAPOSalas. Como posso ajudar com a sua reserva hoje?"; ?>';
            }
            else if (msg.includes('reservar') || msg.includes('marcar') || msg.includes('agendar') || msg.includes('nova reserva')) {
                response = '<?php echo $t['chat_msg_booking'] ?? "Para fazer uma reserva, vá ao menu \"Salas\", escolha a sala desejada e clique em \"Ver Detalhes\". Aí poderá ver a disponibilidade e confirmar o horário."; ?>';
            }
            else if (msg.includes('cancelar') || msg.includes('anular') || msg.includes('desmarcar')) {
                response = '<?php echo $t['chat_msg_cancel'] ?? "Pode cancelar as suas reservas na sua Área Pessoal, no separador \"Minhas Reservas\". Lembre-se de cancelar com antecedência para libertar a sala para outros colegas."; ?>';
            }
            else if (msg.includes('editar') || msg.includes('alterar reserva') || msg.includes('mudar hora')) {
                response = '<?php echo $t['chat_msg_edit'] ?? "Para alterar uma reserva, o ideal é cancelá-la na sua Área Pessoal e fazer uma nova com o horário correto."; ?>';
            }
            else if (msg.includes('horário') || msg.includes('horas') || msg.includes('fechado') || msg.includes('aberto')) {
                response = '<?php echo $t['chat_msg_hours'] ?? "O sistema SAPOSalas está disponível 24/7 para consultas. As salas físicas estão abertas conforme o horário da instituição (geralmente das 08:00 às 20:00)."; ?>';
            }
            else if (msg.includes('login') || msg.includes('entrar') || msg.includes('sessão') || msg.includes('registo')) {
                response = '<?php echo $t['chat_msg_login'] ?? "O login é obrigatório para reservar. Se ainda não tem conta, use a opção \"Criar Conta\". Se já tem, clique em \"Entrar\" no menu principal."; ?>';
            }
            else if (msg.includes('senha') || msg.includes('password') || msg.includes('passe') || msg.includes('esqueci')) {
                response = '<?php echo $t['chat_msg_password'] ?? "Se se esqueceu da senha, utilize a opção \"Recuperar Acesso\" na página de login. Enviaremos um código para o seu email."; ?>';
            }
            else if (msg.includes('problema') || msg.includes('erro') || msg.includes('bug') || msg.includes('não funciona')) {
                response = '<?php echo $t['chat_msg_error'] ?? "Se encontrou um erro técnico, por favor envie um email para admin@saposalas.pt com os detalhes. Vamos resolver o mais rápido possível!"; ?>';
            }
            else if (msg.includes('equipamento') || msg.includes('projetor') || msg.includes('pc') || msg.includes('hdmi')) {
                response = '<?php echo $t['chat_msg_equip'] ?? "Cada sala tem a sua lista de equipamentos (Projetor, AC, PCs) visível nos detalhes da mesma. Verifique a ficha da sala antes de reservar."; ?>';
            }
            else if (msg.includes('sala') || msg.includes('capacidade') || msg.includes('lugares') || msg.includes('tamanho')) {
                response = '<?php echo $t['chat_msg_capacity'] ?? "Temos várias salas com capacidades diferentes. Pode ver a lotação máxima de cada espaço na lista de salas antes de reservar."; ?>';
            }
            else if (msg.includes('onde') || msg.includes('local') || msg.includes('piso') || msg.includes('bloco')) {
                response = '<?php echo $t['chat_msg_location'] ?? "A localização exata (Piso, Bloco ou Edifício) está indicada no cartão de cada sala. Clique em \"Ver Detalhes\" para mais informações."; ?>';
            }
            else if (msg.includes('regras') || msg.includes('comer') || msg.includes('limpeza') || msg.includes('barulho')) {
                response = '<?php echo $t['chat_msg_rules'] ?? "Pedimos que mantenha o espaço limpo e organizado. Não é permitido comer nas salas de aula e deve-se evitar ruído excessivo para não incomodar as salas vizinhas."; ?>';
            }
            else if (msg.includes('confirmar') || msg.includes('email') || msg.includes('certeza')) {
                response = '<?php echo $t['chat_msg_confirm'] ?? "Sempre que faz uma reserva, recebe um email de confirmação. Também pode verificar o estado em tempo real no menu \"Minhas Reservas\"."; ?>';
            }
            else if (msg.includes('antecedência') || msg.includes('tempo') || msg.includes('prazo')) {
                response = '<?php echo $t['chat_msg_advance'] ?? "Pode reservar salas com até 2 semanas de antecedência. Reservas para o próprio dia estão sujeitas à disponibilidade imediata."; ?>';
            }
            else if (msg.includes('obrigado') || msg.includes('tks') || msg.includes('grato')) {
                response = '<?php echo $t['chat_msg_thanks'] ?? "De nada! 😊 Bons estudos e bom trabalho!"; ?>';
            }
            else if (msg.includes('admin') || msg.includes('contacto') || msg.includes('suporte')) {
                response = '<?php echo $t['chat_msg_admin'] ?? "Pode contactar a administração através do email admin@saposalas.pt"; ?>';
            }
            else if (msg.includes('wifi') || msg.includes('wi-fi') || msg.includes('internet') || msg.includes('net') || msg.includes('password')) {
                response = '<?php echo $t['chat_msg_wifi'] ?? "A rede Wi-Fi disponível é a eduroam. Utilize as suas credenciais de estudante/docente para se ligar. Se tiver dificuldades, contacte o departamento de TI."; ?>';
            }
            else if (msg.includes('sujo') || msg.includes('limpeza') || msg.includes('estragado') || msg.includes('partido') || msg.includes('luz')) {
                response = '<?php echo $t['chat_msg_maintenance'] ?? "Lamentamos o incómodo. Por favor, reporte qualquer avaria ou falta de limpeza diretamente na receção ou envie um email com a identificação da sala."; ?>';
            }
            else if (msg.includes('perdi') || msg.includes('esqueci') || msg.includes('perdidos') || msg.includes('deixei')) {
                response = '<?php echo $t['chat_msg_lost'] ?? "Objetos esquecidos nas salas são geralmente entregues na segurança ou na receção do edifício. Verifique lá."; ?>';
            }
            else if (msg.includes('recorrente') || msg.includes('semestre') || msg.includes('semanal') || msg.includes('repetir')) {
                response = '<?php echo $t['chat_msg_recurring'] ?? "Para reservas recorrentes (ex: aulas semestrais), por favor contacte diretamente a secretaria. A plataforma permite apenas reservas pontuais."; ?>';
            }
            else if (msg.includes('chave') || msg.includes('trancada') || msg.includes('abrir')) {
                response = '<?php echo $t['chat_msg_keys'] ?? "As chaves das salas devem ser levantadas na portaria mediante a apresentação do cartão de identificação. Não se esqueça de fechar a sala ao sair."; ?>';
            }
            else if (msg.includes('dados') || msg.includes('privacidade') || msg.includes('gdpr') || msg.includes('rgpd')) {
                response = '<?php echo $t['chat_msg_privacy'] ?? "Os seus dados são utilizados apenas para a gestão de reservas. Pode consultar a nossa Política de Privacidade no rodapé do site ou na sua área pessoal."; ?>';
            }
            else if (msg.includes('sapo')) {
                response = '<?php echo $t['chat_msg_sapo'] ?? "Ribbit! 🐸 O SAPOSalas é a forma mais rápida de saltar para a tua próxima sala de aula!"; ?>';
            }
            else {
                response = '<?php echo $t['chat_msg_default'] ?? "Desculpe, não entendi bem. Pode tentar reformular? Posso ajudar com Reservas, Cancelamentos, Horários, Login, Equipamentos ou Manutenção."; ?>';
            }

            addMessage(response, 'bot');
        }

        setTimeout(() => {
            const suggestions = [
                '<?php echo $t['chat_sugg_booking'] ?? "Como reservar?"; ?>',
                '<?php echo $t['chat_sugg_cancel'] ?? "Cancelar reserva"; ?>',
                '<?php echo $t['chat_sugg_hours'] ?? "Horários"; ?>',
                '<?php echo $t['chat_sugg_report'] ?? "Reportar problema"; ?>'
            ];

            const suggestionsHTML = suggestions.map(s =>
                `<button onclick="document.getElementById('chat-input').value='${s}'; document.getElementById('chat-send-btn').click();" 
             class="text-xs bg-gray-100 dark:bg-gray-700 hover:bg-[#5d3e8f] hover:text-white dark:hover:bg-[#d8b4fe] dark:hover:text-gray-900 text-[#5d3e8f] dark:text-[#d8b4fe] px-3 py-1.5 rounded-full border border-[#5d3e8f]/20 dark:border-[#d8b4fe]/20 transition-colors mb-2 mr-2">
             ${s}
             </button>`
            ).join('');

            const messageDiv = document.createElement('div');
            messageDiv.className = 'flex w-full mb-4 justify-start';
            messageDiv.innerHTML = `
            <div class="w-8 h-8 rounded-full bg-[#5d3e8f] flex items-center justify-center text-white text-xs flex-shrink-0 mr-2 shadow-sm"><i class="fas fa-robot"></i></div>
            <div class="flex flex-col max-w-[85%]">
                <div class="bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-600 rounded-r-2xl rounded-tl-2xl px-4 py-2.5 shadow-sm text-sm leading-relaxed mb-2">
                    <?php echo $t['chat_sugg_title'] ?? "Aqui estão algumas sugestões rápidas:"; ?>
                </div>
                <div class="flex flex-wrap">
                    ${suggestionsHTML}
                </div>
            </div>
        `;
            chatBody.appendChild(messageDiv);
        }, 1000);

        setTimeout(() => {
            if (chatWindow.classList.contains('hidden')) {
                chatNotification.style.display = 'flex';
            }
        }, 4000);
    });
</script>