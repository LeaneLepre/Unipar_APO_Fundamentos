## Gerenciador Logístico em PHP

Atividade prática orientada da disciplina de Fundamentos de Programação para Internet.  
Curso de Análise e Desenvolvimento de Sistemas.  
Unipar EAD.

### Tecnologias Utilizadas
- PHP para backend
- HTML5, CSS3 e JavaScript para frontend
- Framework Bootstrap
- Xampp e Apache para testes e desenvolvimento
- SQLite através de PDO
- Git e GitHub para versionamento

### Funcionalidades Implementadas
- Painel inicial.
- Cadastro, edição e exclusão de produtos, endereços e transportadores.
- Geração de fretes com transportador, data, origem, destino, seleção de produtos e status de entrega.
- Relatório de fretes por dia com exportação em PDF.

### Organização Utilizada
- assets/css/ - style.css e assets/js/ - app.js.
- config/ - database.php.
- includes/ - header.php e footer.php.
- pages/ - products.php, addresses.php, carriers.php, freights.php e report.php.
- index.php
- database/

### Observações
Como este é um exercício acadêmico, os campos CPF/CNPJ e CEP são armazenados sem validação de autenticidade.  
Verifique se a extensão PDO_SQLite está habilitada no PHP.