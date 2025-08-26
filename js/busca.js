document.addEventListener('DOMContentLoaded', function() {
    const resultsContainer = document.querySelector('.search-results-container');

    resultsContainer.addEventListener('click', function(e) {
        const menuBtn = e.target.closest('.menu-btn');
        
        document.querySelectorAll('.menu-dropdown').forEach(menu => {
            if (!menu.parentElement.contains(e.target)) {
                menu.style.display = 'none';
            }
        });

        if (menuBtn) {
            e.preventDefault();
            const dropdown = menuBtn.nextElementSibling;
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }

        const editBtn = e.target.closest('.edit-btn');
        if (editBtn) {
            e.preventDefault();
            const resultItem = editBtn.closest('.result-item');
            const assuntoId = resultItem.dataset.assuntoId;
            const tituloAtual = resultItem.dataset.titulo;
            const novoTitulo = prompt("Digite o novo nome para o assunto:", tituloAtual);

            if (novoTitulo && novoTitulo.trim() !== '' && novoTitulo.trim() !== tituloAtual) {
                const formData = new FormData();
                formData.append('action', 'edit_assunto');
                formData.append('assunto_id', assuntoId);
                formData.append('novo_titulo', novoTitulo.trim());

                fetch('dashboard.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            resultItem.querySelector('h3').textContent = novoTitulo.trim();
                            resultItem.dataset.titulo = novoTitulo.trim();
                        } else {
                            alert('Erro ao editar: ' + (data.message || 'Tente novamente.'));
                        }
                    });
            }
        }

        const deleteBtn = e.target.closest('.delete-btn');
        if (deleteBtn) {
            e.preventDefault();
            if (confirm('Tem certeza que deseja apagar este assunto? Todas as suas revisões serão removidas permanentemente.')) {
                const resultItem = deleteBtn.closest('.result-item');
                const assuntoId = resultItem.dataset.assuntoId;
                
                const formData = new FormData();
                formData.append('action', 'delete_assunto');
                formData.append('assunto_id', assuntoId);
                
                fetch('dashboard.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            resultItem.style.opacity = '0';
                            setTimeout(() => resultItem.remove(), 300);
                        } else {
                            alert('Erro ao apagar: ' + (data.message || 'Tente novamente.'));
                        }
                    });
            }
        }
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.result-menu-container')) {
            document.querySelectorAll('.menu-dropdown').forEach(menu => {
                menu.style.display = 'none';
            });
        }
    });
});