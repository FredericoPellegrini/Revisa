
Revisa é um sistema de estudos projetado para auxiliar estudantes a organizar e reter conteúdo de forma eficiente. A aplicação utiliza a técnica de 

Repetição Espaçada (Spaced Repetition), um método cientificamente validado que combate a curva do esquecimento e ajuda a fixar o conhecimento na memória de longo prazo. 

O objetivo é oferecer uma ferramenta simples e intuitiva onde o usuário possa cadastrar os tópicos que está estudando e receber um cronograma de revisões gerado automaticamente, maximizando o aprendizado sem a necessidade de conhecimentos prévios sobre métodos de estudo. 

Este projeto foi desenvolvido no âmbito acadêmico do IFBA - Campus Valença. 

Funcionalidades Principais

O sistema foi desenhado com foco na simplicidade e eficiência, permitindo que os usuários:
  
  🔐 Autenticação Segura: Realizar cadastro e login de forma segura. 
  
  📚 Cadastro de Matérias: Adicionar os tópicos ou conteúdos que precisam ser revisados. 
  
  🏷️ Organização com Tags: Atribuir tags aos conteúdos para facilitar a filtragem e organização (Ex: Matéria: Crase, Tag: Português). 
  
  🧠 Algoritmo de Repetição Espaçada: O sistema calcula e gera automaticamente as datas ideais para cada revisão, baseando-se em intervalos crescentes (1, 7, 14, 30 dias, etc.). 
  
  🗓️ Calendário de Revisões: Visualizar todas as revisões agendadas em um calendário mensal intuitivo. 
  
  ✅ Gerenciamento de Progresso: Marcar revisões como concluídas e acompanhar o andamento dos estudos. 
  
  ✏️ Edição e Exclusão: Gerenciar os assuntos e tags, podendo editá-los ou removê-los.


Tecnologias Utilizadas

O projeto foi construído com as seguintes tecnologias: 

    Frontend: HTML5, CSS3, JavaScript

    Backend: PHP (sem o uso explícito de frameworks como Laravel, conforme visto no dashboard.php)

    Banco de Dados: MySQL

    Autenticação: Sessões gerenciadas pelo Backend

💾 Estrutura do Banco de Dados

O sistema utiliza um banco de dados MySQL chamado sistema_revisao. As principais tabelas são:

    users: Armazena as informações dos usuários cadastrados (nome, email, senha).

    assuntos: Guarda os tópicos de estudo que cada usuário cadastra.

    tags: Armazena as tags criadas pelos usuários para categorizar os assuntos.

    assunto_tag: Tabela pivot para relacionar os assuntos às suas respectivas tags (relação N:N).

    revisoes: Tabela principal do sistema, onde cada revisão de um assunto é agendada com uma data específica e um status (feita/não feita).

    password_resets: Utilizada para o fluxo de recuperação de senha.
