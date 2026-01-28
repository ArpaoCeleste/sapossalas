PT

--The English version can be found below this section--

# ![SAPOSalas](https://img.shields.io/badge/SAPOSalas-v1.5.0-8A2BE2?style=for-the-badge&logo=php&logoColor=white)

> **Sistema Integrado de Gestão e Reserva de Salas.**
> Uma solução completa com Backoffice, Autenticação a 2 Fatores (2FA) e API JSON.

![Status](https://img.shields.io/badge/Status-Operacional-blueviolet?style=flat-square)
![Tech](https://img.shields.io/badge/Backend-PHP_8-9370DB?style=flat-square)
![Docs](https://img.shields.io/badge/Docs-Swagger_OpenAPI-mediumpurple?style=flat-square)

---

## 🟣 Acessos Rápidos

Toda a documentação técnica dos endpoints e a interface visual da API encontram-se nos links abaixo:

| Recurso | Link | Descrição |
| :--- | :--- | :--- |
| **Website (Produção)** | [sapossalas.rf.gd](https://sapossalas.rf.gd) | Aplicação funcional no InfinityFree. |
| **Documentação API** | [Swagger UI](https://arpaoceleste.github.io/saposalas-api-docs/) | Interface visual interativa dos endpoints. |
| **Documentação API** | [Swagger UI](https://sapossalas.rf.gd/api-docs/#/) | Interface visual interativa dos endpoints no InfinityFree. |
| **Repo da Doc** | [GitHub Docs](https://github.com/arpaoceleste/saposalas-api-docs) | Repositório da documentação Swagger (OpenAPI). |

---

## 🟪 Sobre o Projeto

O **SAPOSalas** é uma solução web robusta e centralizada, desenhada para modernizar a gestão de espaços corporativos. O projeto resolve o problema comum de conflitos de agendamento e falta de visibilidade sobre a ocupação de salas, oferecendo uma interface intuitiva tanto para colaboradores como para a administração.

A arquitetura do sistema privilegia a segurança, a rapidez de resposta (performance) e a facilidade de manutenção, utilizando tecnologias nativas e práticas de desenvolvimento modernas.

### 👥 Perfis de Acesso
O sistema define claramente dois níveis de interação:

1.  **Utilizador Padrão (Colaborador):** Pode pesquisar salas, consultar detalhes, efetuar reservas próprias e gerir o seu perfil.
2.  **Administrador:** Possui privilégios totais sobre o sistema, podendo gerir todas as reservas, editar dados mestre (salas/utilizadores) e aceder a relatórios de ocupação.

---

## 🟣 Stack Tecnológico

O projeto foi construído utilizando tecnologias nativas e modernas para garantir desempenho e compatibilidade.

| Categoria | Tecnologias |
| :--- | :--- |
| **Frontend** | ![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=flat-square&logo=html5&logoColor=white) ![TailwindCSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=flat-square&logo=tailwind-css&logoColor=white) ![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat-square&logo=javascript&logoColor=black) |
| **Backend** | ![PHP](https://img.shields.io/badge/PHP_8-777BB4?style=flat-square&logo=php&logoColor=white) ![PDO](https://img.shields.io/badge/PDO-MySQL-4479A1?style=flat-square) |
| **Base de Dados** | ![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white) |
| **Ferramentas** | ![Swagger](https://img.shields.io/badge/Swagger-85EA2D?style=flat-square&logo=swagger&logoColor=black) ![Git](https://img.shields.io/badge/Git-F05032?style=flat-square&logo=git&logoColor=white) ![XAMPP](https://img.shields.io/badge/XAMPP-FB7A24?style=flat-square&logo=xampp&logoColor=white) |

---

## 🟪 Detalhes Técnicos e Funcionalidades

Segurança de Nível Empresarial 

O sistema SAPOSalas integra múltiplas camadas de defesa, visando a prevenção ativa contra ameaças comuns e a proteção rigorosa dos dados do utilizador.

   ```text
Autenticação Robusta:
    Login protegido com hashing avançado (Bcrypt) e Autenticação de 2 Fatores (2FA) flexível (Email ou Google Authenticator), garantindo que apenas o proprietário legítimo aceda à conta.

Defesa contra Brute-Force e Spam:
    Sistema de Rate Limiting que bloqueia temporariamente IPs após múltiplas tentativas de login ou registo falhadas, com tempo de penalização progressivo.

    Anti-Bot Comportamental: Utilização de técnicas como Honeypot (campos ocultos) e verificação de tempo de submissão (microtime) para bloquear bots sem afetar utilizadores reais.

    Google reCAPTCHA v3: Integração para distinguir tráfego humano de tráfego automatizado malicioso.

Proteção de Dados e Integridade (OWASP Top 10):
    Injeção de SQL: Uso exclusivo de Prepared Statements (mysqli) para mitigar ataques de injeção.

    CSRF (Cross-Site Request Forgery): Implementação de tokens anti-CSRF em todas as submissões de formulário (POST).

    XSS (Cross-Site Scripting): Sanitização rigorosa de inputs e outputs (escapamento de caracteres especiais) para impedir injeção de scripts.

Gestão Avançada de Sessões e Cookies:
    Sessões: Regeneração de ID de sessão após o login (prevenção de Session Fixation) e controlo de inatividade.

    Cookies Blindados: Configuração das flags HttpOnly (impede roubo via JavaScript) e Secure (transmissão exclusiva via HTTPS), com SameSite=Lax para reforço CSRF.

Política de Password Forte:
    Aplicação de requisitos mínimos de complexidade via Regex no registo: Mínimo 8 caracteres, obrigatório conter maiúsculas, minúsculas, número e símbolo.

Uploads Seguros e Isolamento de Media:
    Validação de tipos MIME e renomeação de ficheiros.

    Integração com Cloudinary para o armazenamento de imagens de galeria, isolando o servidor da aplicação de potenciais ameaças de Remote Code Execution (RCE) via ficheiros maliciosos.

Auditoria e Logs:
    Registo detalhado (writeLog) de IPs e timestamps para todas as ações críticas (Falhas de Login, Registo, 2FA) para análise de incidentes.

  ```

🟪 Experiência de Utilizador Fluida (UX)

A prioridade no desenvolvimento foi criar uma experiência rápida, moderna e intuitiva, minimizando a frustração do utilizador com tempos de espera e garantindo a acessibilidade em qualquer dispositivo.

    Navegação Assíncrona (AJAX/Fetch API):

        Performance: Utilização intensiva de chamadas assíncronas (AJAX e Fetch API) para carregar dados pesados, como grelhas de horários e detalhes de reservas, sem recarregamentos de página desnecessários.

        Interactividade em Tempo Real: Permite atualizações imediatas no estado das salas e filtros dinâmicos, proporcionando um feedback rápido e moderno ao utilizador.

        Modais Dinâmicos: Abertura rápida de janelas modais para edição ou visualização de detalhes (e.g., Detalhes da Sala, Edição de Perfil) com carregamento de conteúdo via API, otimizando o fluxo de trabalho.

    Interface Responsiva e Acessível:

        Mobile-First com Tailwind CSS: O layout foi construído com a metodologia mobile-first e classes utilitárias do Tailwind CSS, garantindo uma adaptação perfeita a telemóveis, tablets e ecrãs de desktop (o que se designa por Responsive Design).

        Consistência Visual: Manutenção de um design system consistente (incluindo o tema escuro/dark mode) em todas as páginas, melhorando a facilidade de uso.

        Navegação Intuitiva: Estrutura simples de navegação e dashboards laterais (sidebars) que se recolhem automaticamente em ecrãs mais pequenos, maximizando o espaço de trabalho útil.

    Validação em Tempo Real:

        Feedback imediato de erros (e.g., passwords que não coincidem ou campos vazios) diretamente no formulário, minimizando o tempo de correção.



⚙️ Backoffice Administrativo Completo

O módulo administrativo foi desenvolvido para ser um ponto de controlo centralizado, oferecendo ao utilizador com a role de admin todas as ferramentas necessárias para gerir eficientemente os recursos, utilizadores e o fluxo de reservas.

    Dashboard de Controlo & Métricas:

        Visão Geral: Apresentação de um dashboard com métricas e dados resumidos sobre o estado do sistema, a taxa de ocupação das salas e o número total de utilizadores registados.

        Logs de Segurança: Acesso a logs detalhados de atividades (incluindo tentativas de login e ações de 2FA) para auditoria e análise de segurança.

    Gestão de Recursos (CRUD de Salas):

        Controlo Total (CRUD): Funcionalidade completa de Criar, Ler, Atualizar e Eliminar salas (recursos), incluindo a definição de localização, capacidade e equipamentos disponíveis.

        Gestão de Galeria: Sistema de gestão de fotografias integrado com Cloudinary, garantindo o upload seguro e o alojamento externo das imagens da galeria de cada sala.

    Gestão de Reservas:

        Monitorização de Conflitos: Capacidade de visualizar, editar e cancelar todas as reservas no sistema, permitindo ao administrador resolver proactivamente quaisquer conflitos de agendamento ou gerir o tempo de utilização das salas.

        Visualização Detalhada: Acesso a todos os detalhes da reserva, incluindo quem reservou, porquê e por quanto tempo, através de uma interface de calendário interativa (admin.php).

    Gestão de Pessoas e Acessos:

        Edição de Perfis: Capacidade de aceder e editar informações de perfis de utilizadores (como nome, contacto e role).

        Controlo de Permissões: Redefinição de privilégios e acessos, com a atribuição de roles específicas (e.g., admin vs. user) para controlo de acesso à interface administrativa.

🗓️ Sistema de Agendamento Inteligente

O sistema de agendamento foi desenhado para ser robusto, prevenindo erros de reserva e fornecendo ao utilizador todas as informações necessárias para tomar decisões rápidas e informadas.

    Validação Automática de Conflitos e Regras de Agendamento:

        Prevenção de Sobreposição: Validação automática no backend para garantir que não há conflitos de horário entre reservas na mesma sala, utilizando lógica de interseção temporal (o coração do processar_reserva.php).

        Regra de Antecedência (12h): Aplicação de uma regra de negócio que exige que todas as reservas ou edições sejam feitas com, pelo menos, 12 horas de antecedência relativamente ao horário de início.

        Sugestões Inteligentes: Em caso de conflito, o sistema não só nega a reserva, mas também gera e exibe sugestões de horários livres alternativos na mesma data e sala.

    Visibilidade e Feedback em Tempo Real:

        Grelha de Ocupação: Apresentação de uma Grelha Horária (Occupancy Grid) que oferece feedback visual imediato sobre a disponibilidade da sala, permitindo ao utilizador ver rapidamente os blocos ocupados e livres.

        Atualizações Dinâmicas: A informação da grelha é obtida via API (AJAX) e atualizada regularmente, garantindo que o estado de ocupação é sempre o mais recente.

        Status de Reserva: Classificação clara das reservas em "Ativa" (futura) e "Concluída" (passada) no painel do utilizador.

    Filtros Avançados e Pesquisa Eficiente:

        Filtros por Recurso: Pesquisa e filtragem de salas por Capacidade Mínima e Localização (e.g., Piso, Edifício).

        Consulta por Data: Permite ao utilizador selecionar qualquer data futura para verificar a ocupação, facilitando o planeamento a longo prazo.
---

🟪 Instalação e Execução Local

Para correres este projeto na tua máquina local (ambiente XAMPP/MAMP), segue os passos abaixo.
Pré-requisitos

    Servidor Local: XAMPP (ou outro servidor com Apache e MySQL).

    Sistema de Controlo de Versões: Git instalado.

    Composer: Necessário para instalar dependências do PHP (ex: PHPMailer, phpdotenv).

Passo 1: Obter o Código

    Navega até à pasta htdocs do teu XAMPP (ou a pasta do teu servidor local).

    Clona o repositório para uma nova pasta, por exemplo, saposalas:

Bash

cd C:\xampp\htdocs
git clone -b main https://github.com/ArpaoCeleste/sapossalas.git 
cd saposalas

    Instala as dependências do PHP usando o Composer:

Bash

composer install

Passo 2: Configurar a Base de Dados

    Abre o phpMyAdmin no teu navegador (http://localhost/phpmyadmin).

    Cria uma nova base de dados chamada saposalas.

    Importa o ficheiro de estrutura da base de dados, que se encontra no projeto:
    sapo.sql 

Passo 3: Variáveis de Ambiente

Este passo é crucial, pois as credenciais de servidor e serviços externos (e-mail, Cloudinary) são carregadas a partir do ficheiro .env e isoladas do controlo de versões.

    Criação do Ficheiro: Copia o ficheiro .env.example para .env na raiz do projeto.
    Bash

cp .env.example .env

Edição: Preenche o ficheiro .env com as tuas credenciais de acesso, garantindo que a base de dados local está acessível.
Ini, TOML

    DB_HOST=localhost
    DB_NAME=saposalas
    DB_USER=root
    DB_PASS= # Deixa vazio ou usa a password do teu XAMPP

    SMTP_HOST=smtp.gmail.com
    SMTP_USER=exemplo@gmail.com
    SMTP_PASS=app_password # Usar a App Password do Gmail
    SMTP_PORT=587 # Porta padrão para TLS

    RECAPTCHA_SITE_KEY=sua_chave_pública
    RECAPTCHA_SECRET_KEY=sua_chave_secreta

    CLOUDINARY_CLOUD_NAME=oseu_cloud_name
    CLOUDINARY_API_KEY=asua_api_key
    CLOUDINARY_API_SECRET=asua_api_secret

Passo 4: Executar o Projeto

    Garante que o Apache e o MySQL estão a correr no teu XAMPP.

    Abre o teu navegador e acede ao URL:

http://localhost/saposalas


⚙️ Configuração e Ambientes

Esta secção detalha as considerações de infraestrutura e deployment do projeto, essenciais para garantir que a aplicação funciona corretamente, quer em ambiente de desenvolvimento local, quer em produção.
1. Diferenças de Ambiente e Deployment

⚠️ Nota Crítica sobre o Alojamento (InfinityFree)

O projeto exige atenção no momento do deployment, devido às restrições do ambiente de alojamento gratuito:

    Ambiente Local (XAMPP/MAMP): O código PHP utiliza caminhos absolutos baseados na raiz do servidor local (ex: /saposalas/imagens/).

    Ambiente de Produção (InfinityFree): Foram aplicadas alterações específicas nos caminhos e nos redirecionamentos PHP para compatibilidade com a estrutura de pastas e as permissões do alojamento free tier.

    Aviso: Ao fazer deploy ou migrar o projeto, é fundamental verificar e ajustar as constantes de caminho e quaisquer referências externas no CSS ou JavaScript para garantir que todos os recursos são carregados corretamente,pode haver diferenças entre o estilo do website no Localhost e no InfinityFree.

2. Variáveis de Ambiente e Credenciais

Para a aplicação funcionar, é obrigatório criar um ficheiro .env na raiz do projeto contendo as credenciais da base de dados e configurações de serviços externos (e-mail, Cloudinary, reCAPTCHA).

    Carregamento Seguro: Estas variáveis são carregadas de forma segura através do config.php, sendo isoladas do código fonte (boas práticas de segurança).

🟣 Apoio e Contactos

Para questões técnicas, reportar vulnerabilidades de segurança ou dúvidas sobre a integração, utilize o contacto da equipa.
Ponto de Contacto	Detalhes
Equipa	SAPOSalas
Email	admin@saposalas.pt
Suporte	Suporte técnico para bugs críticos, falhas de segurança e questões de implementação.





EN

# ![SAPOSalas](https://img.shields.io/badge/SAPOSalas-v1.5.0-8A2BE2?style=for-the-badge&logo=php&logoColor=white)

> **Integrated Room Management and Booking System.**
> A complete solution featuring a Backoffice, Two-Factor Authentication (2FA), and a JSON API.

![Status](https://img.shields.io/badge/Status-Operational-blueviolet?style=flat-square)
![Tech](https://img.shields.io/badge/Backend-PHP_8-9370DB?style=flat-square)
![Docs](https://img.shields.io/badge/Docs-Swagger_OpenAPI-mediumpurple?style=flat-square)

---

## 🟣 Quick Access

All technical documentation and the visual API interface can be found below:

| Resource | Link | Description |
| :--- | :--- | :--- |
| **Website (Production)** | [sapossalas.rf.gd](https://sapossalas.rf.gd) | Functional application hosted on InfinityFree. |
| **API Documentation** | [Swagger UI](https://arpaoceleste.github.io/saposalas-api-docs/) | Interactive visual interface for the endpoints (Github). |
| **API Documentation** | [Swagger UI](https://sapossalas.rf.gd/api-docs/#/) | Interactive visual interface for the endpoints (Production). |
| **Docs Repo** | [GitHub Docs](https://github.com/arpaoceleste/saposalas-api-docs) | Swagger Documentation Repository (OpenAPI). |

---

## 🟪 About the Project

The **SAPOSalas** is a robust, centralized web solution designed to modernize the management of corporate spaces. The project solves the common problem of scheduling conflicts and lack of visibility regarding room occupancy, offering an intuitive interface for both staff and administration.

The system's architecture prioritizes security, rapid response (performance), and ease of maintenance, using native technologies and modern development practices.

### 👥 Access Profiles
The system clearly defines two levels of interaction:

1.  **Standard User (Collaborator):** Can search rooms, check details, make their own reservations, and manage their profile.
2.  **Administrator:** Has full privileges over the system, managing all reservations, editing master data (rooms/users), and accessing occupancy reports.

---

## 🟣 Tech Stack

The project was built using native and modern technologies to ensure performance and compatibility.

| Category | Technologies |
| :--- | :--- |
| **Frontend** | ![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=flat-square&logo=html5&logoColor=white) ![TailwindCSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=flat-square&logo=tailwind-css&logoColor=white) ![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat-square&logo=javascript&logoColor=black) |
| **Backend** | ![PHP](https://img.shields.io/badge/PHP_8-777BB4?style=flat-square&logo=php&logoColor=white) ![PDO](https://img.shields.io/badge/PDO-MySQL-4479A1?style=flat-square) |
| **Database** | ![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white) |
| **Tools** | ![Swagger](https://img.shields.io/badge/Swagger-85EA2D?style=flat-square&logo=swagger&logoColor=black) ![Git](https://img.shields.io/badge/Git-F05032?style=flat-square&logo=git&logoColor=white) ![XAMPP](https://img.shields.io/badge/XAMPP-FB7A24?style=flat-square&logo=xampp&logoColor=white) |

---

## 🟪 Technical Details and Features

### 🔐 Enterprise-Level Security

The SAPOSalas system integrates multiple defense layers, aiming for active prevention against common threats and rigorous user data protection.

* **Robust Authentication:** Login protected with advanced hashing (Bcrypt) and Two-Factor Authentication (2FA) via email, ensuring only the legitimate owner accesses the account.
* **Brute-Force and Spam Defense:**
    * **Rate Limiting System** that temporarily blocks IPs after multiple failed login or registration attempts, with progressive penalty time.
    * **Behavioral Anti-Bot:** Use of techniques like **Honeypot** (hidden fields) and submission time verification (`microtime`) to block bots without affecting real users.
    * **Google reCAPTCHA:** Integration to distinguish human traffic from malicious automated traffic.
* **Data Integrity and Protection (OWASP Top 10):**
    * **SQL Injection:** Exclusive use of **Prepared Statements** (PDO/mysqli) to mitigate injection attacks.
    * **CSRF (Cross-Site Request Forgery):** Implementation of anti-CSRF tokens in all form submissions (POST).
    * **XSS (Cross-Site Scripting):** Rigorous sanitization of inputs and outputs (escaping special characters) to prevent code injection.
* **Advanced Session and Cookie Management:**
    * **Sessions:** Session ID regeneration after login (prevention of *Session Fixation*) and inactivity control.
    * **Hardened Cookies:** Configuration of **`HttpOnly`** (prevents theft via JavaScript) and **`Secure`** (exclusive transmission via HTTPS in production) flags, with `SameSite=Lax` for CSRF reinforcement.
* **Strong Password Policy:** Application of minimum complexity requirements via Regex during registration: Minimum 8 characters, mandatory uppercase, lowercase, number, and symbol.
* **Secure Uploads and Media Isolation:**
    * MIME type validation and file renaming.
    * Integration with **Cloudinary** for gallery image storage, isolating the application server from potential **Remote Code Execution (RCE)** threats via malicious files.
* **Auditing and Logs:** Detailed logging (`writeLog`) of IPs and timestamps for all critical actions (Login Failures, Registration, 2FA) for incident analysis.

### 🟪 Fluid User Experience (UX)

The development priority was to create a fast, modern, and intuitive experience, minimizing user frustration with waiting times and ensuring accessibility on any device.

* **Asynchronous Navigation (AJAX/Fetch API):**
    * **Performance:** Intensive use of asynchronous calls (AJAX and Fetch API) to load heavy data, such as schedule grids and reservation details, **without unnecessary page reloads**.
    * **Dynamic Modals:** Quick opening of modal windows for editing or viewing details (e.g., Room Details, Profile Edit) with content loaded via API, optimizing the workflow.
* **Responsive and Accessible Interface:**
    * **Mobile-First with Tailwind CSS:** The layout was built with the mobile-first methodology and utility classes of **Tailwind CSS**, ensuring **perfect adaptation** to mobile phones, tablets, and desktop screens (*Responsive Design*).
    * **Real-Time Validation:** Immediate feedback on errors (e.g., passwords mismatch, empty fields) directly in the form, minimizing correction time.

### ⚙️ Complete Administrative Backoffice

The administrative module was developed to be a **centralized control point**, offering the user with the `admin` role all the necessary tools to efficiently manage resources, users, and the booking flow.

* **Control Dashboard & Metrics:**
    * **Overview:** Presentation of a dashboard with summarized metrics and data on the system status, room occupancy rate, and total registered users.
    * **Security Logs:** Access to detailed activity logs (including 2FA actions) for security auditing.
* **Resource Management (Room CRUD):**
    * **Total Control (CRUD):** Full functionality to Create, Read, Update, and Delete rooms, including location, capacity, and equipment definitions.
    * **Gallery Management:** Integration with Cloudinary for secure file upload and external hosting of gallery images.
* **Booking Management:**
    * **Conflict Monitoring:** Ability to view, edit, and **cancel all reservations**, allowing the administrator to proactively resolve scheduling conflicts.

### 🗓️ Smart Scheduling System

The scheduling system was designed to be robust, preventing booking errors and providing the user with all necessary information for quick and informed decisions.

* **Automatic Conflict Validation and Rules:**
    * **Overlap Prevention:** Automatic *backend* validation to ensure no time conflicts between reservations using temporal intersection logic.
    * **12h Advance Rule:** Application of a business rule requiring all reservations or edits to be made at least **12 hours in advance** of the start time.
    * **Intelligent Suggestions:** In case of conflict, the system denies the reservation and generates and displays **alternative free time slots**.
* **Visibility and Real-Time Feedback:**
    * **Occupancy Grid:** Presentation of a **Hourly Grid** (*Occupancy Grid*) offering immediate visual feedback on room availability.

---

## 🟪 Local Installation and Execution

To run this project on your local machine (XAMPP/MAMP environment), follow the steps below.

### Prerequisites
* **Local Server:** XAMPP (or another server with Apache and MySQL).
* **Version Control:** Git installed.
* **Dependencies:** Composer is required to install PHP dependencies (e.g., PHPMailer, phpdotenv).

### Passo 1: Get the Code

1.  Navigate to your XAMPP's `htdocs` folder.
2.  Clone the repository and enter the project folder (e.g., `saposalas`):

```bash
cd C:\xampp\htdocs
git clone -b main https://github.com/ArpaoCeleste/sapossalas.git 
cd saposalas
```
Install PHP dependencies using Composer:

Bash

composer install

Passo 2: Database Setup

    Open phpMyAdmin in your browser (http://localhost/phpmyadmin).

    Create a new database named saposalas.

    Import the database structure file located in the project: sapo.sql.

Passo 3: Environment Variables

This step is crucial as server and external service credentials (email, Cloudinary) are loaded from the .env file and isolated from version control.

    File Creation: Copy the example file, renaming it from .env.example to .env in the project root.
    Bash

cp .env.example .env

Editing: Fill in your access credentials in the .env file, ensuring the local database is accessible.
Ini, TOML

    DB_HOST=localhost
    DB_NAME=saposalas
    DB_USER=root
    DB_PASS= # Leave blank or use your XAMPP password

    SMTP_HOST=smtp.gmail.com
    SMTP_USER=exemplo@gmail.com
    SMTP_PASS=app_password # Use the Gmail App Password
    SMTP_PORT=587 # Default port for TLS

    RECAPTCHA_SITE_KEY=your_public_key
    RECAPTCHA_SECRET_KEY=your_secret_key

    CLOUDINARY_CLOUD_NAME=your_cloud_name
    CLOUDINARY_API_KEY=your_api_key
    CLOUDINARY_API_SECRET=your_api_secret

Passo 4: Run the Project

    Ensure that Apache and MySQL are running in your XAMPP.

    Open your browser and navigate to the URL:

http://localhost/saposalas

⚙️ Configuration and Environments
1. Deployment and Environment Differences

⚠️ Critical Note on Hosting (InfinityFree)

The project requires attention during deployment due to the restrictions of the free hosting environment:

    Local Environment (XAMPP/MAMP): PHP code uses absolute paths based on the local server root (e.g., /saposalas/images/).

    Production Environment (InfinityFree): Specific changes were applied to PHP paths and redirects for compatibility with the free tier hosting structure. Also, there might be differences between the website style on Localhost and InfinityFree.

    Warning: When deploying or migrating the project, it is essential to check and adjust path constants and any external references in CSS or JavaScript to ensure all resources are loaded correctly.

2. Environment Variables and Credentials

    Secure Loading: These variables are loaded securely via config.php, being isolated from the source code (good security practices).

🟣 Support and Contact

For technical questions, reporting security vulnerabilities, or integration inquiries, use the team's contact information.
Contact Point	Details
Team	SAPOSalas
Email	admin@saposalas.pt
Support	Technical support for critical bugs, security flaws, and implementation questions.

🟣 Licença

Este projeto está licenciado sob a Licença MIT.

🟣 Autor

ArpaoCeleste
