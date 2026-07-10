/**
 * JavaScript Principal do Sistema
 */

// Formatar valores monetários
function formatarMoeda(input) {
    let valor = input.value.replace(/\D/g, '');
    valor = (valor / 100).toFixed(2) + '';
    valor = valor.replace(".", ",");
    valor = valor.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
    input.value = valor;
}

// Aplicar máscara de moeda em campos
document.addEventListener('DOMContentLoaded', function() {
    const camposMoeda = document.querySelectorAll('input[name="valor"], input[name="valor_unitario"]');
    camposMoeda.forEach(campo => {
        campo.addEventListener('input', function() {
            formatarMoeda(this);
        });
    });
});

// Confirmar exclusão
function confirmarExclusao(mensagem = 'Tem certeza que deseja excluir?') {
    return confirm(mensagem);
}

// Mostrar loading
function mostrarLoading() {
    // Implementar loading se necessário
}

// Esconder loading
function esconderLoading() {
    // Implementar loading se necessário
}

// Validar formulário
function validarFormulario(formId) {
    const form = document.getElementById(formId);
    if (form) {
        return form.checkValidity();
    }
    return false;
}

// Limpar formulário
function limparFormulario(formId) {
    const form = document.getElementById(formId);
    if (form) {
        form.reset();
    }
}

// Máscara de telefone
function mascaraTelefone(input) {
    let valor = input.value.replace(/\D/g, '');
    if (valor.length <= 10) {
        valor = valor.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
    } else {
        valor = valor.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    }
    input.value = valor;
}

// Máscara de CEP
function mascaraCEP(input) {
    let valor = input.value.replace(/\D/g, '');
    valor = valor.replace(/(\d{5})(\d{3})/, '$1-$2');
    input.value = valor;
}

// Máscara de CNPJ
function mascaraCNPJ(input) {
    let valor = input.value.replace(/\D/g, '');
    valor = valor.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
    input.value = valor;
}

// Máscara de CPF
function mascaraCPF(input) {
    let valor = input.value.replace(/\D/g, '');
    valor = valor.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    input.value = valor;
}

// Auto-detectar CPF ou CNPJ
function mascaraCPFCNPJ(input) {
    let valor = input.value.replace(/\D/g, '');
    if (valor.length <= 11) {
        mascaraCPF(input);
    } else {
        mascaraCNPJ(input);
    }
}

// Aplicar máscaras automaticamente
document.addEventListener('DOMContentLoaded', function() {
    // Telefone
    const camposTelefone = document.querySelectorAll('input[name="telefone"]');
    camposTelefone.forEach(campo => {
        campo.addEventListener('input', function() {
            mascaraTelefone(this);
        });
    });
    
    // CEP
    const camposCEP = document.querySelectorAll('input[name="cep"]');
    camposCEP.forEach(campo => {
        campo.addEventListener('input', function() {
            mascaraCEP(this);
        });
    });
    
    // CNPJ/CPF
    const camposCNPJCPF = document.querySelectorAll('input[name="cnpj_cpf"]');
    camposCNPJCPF.forEach(campo => {
        campo.addEventListener('input', function() {
            mascaraCPFCNPJ(this);
        });
    });
});

// Buscar CEP via API
async function buscarCEP(cep) {
    cep = cep.replace(/\D/g, '');
    if (cep.length === 8) {
        try {
            const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
            const data = await response.json();
            if (!data.erro) {
                return data;
            }
        } catch (error) {
            console.error('Erro ao buscar CEP:', error);
        }
    }
    return null;
}

// Preencher endereço automaticamente
document.addEventListener('DOMContentLoaded', function() {
    const campoCEP = document.querySelector('input[name="cep"]');
    if (campoCEP) {
        campoCEP.addEventListener('blur', async function() {
            const dados = await buscarCEP(this.value);
            if (dados) {
                const campoEndereco = document.querySelector('input[name="endereco"]');
                const campoCidade = document.querySelector('input[name="cidade"]');
                const campoEstado = document.querySelector('select[name="estado"]');
                
                if (campoEndereco && dados.logradouro) {
                    campoEndereco.value = dados.logradouro;
                }
                if (campoCidade && dados.localidade) {
                    campoCidade.value = dados.localidade;
                }
                if (campoEstado && dados.uf) {
                    campoEstado.value = dados.uf;
                }
            }
        });
    }
});


/**
 * Controle de Abas para O.S. Atrasadas
 */
document.addEventListener('DOMContentLoaded', function() {
    // Selecionar todos os botões de abas
    const tabBtns = document.querySelectorAll('.tab-btn');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            
            // Remover classe ativa de todos os botões
            tabBtns.forEach(b => b.classList.remove('tab-btn-active'));
            
            // Adicionar classe ativa ao botão clicado
            this.classList.add('tab-btn-active');
            
            // Esconder todos os conteúdos de abas
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('tab-content-active'));
            
            // Mostrar o conteúdo da aba selecionada
            const selectedTab = document.getElementById(tabName);
            if (selectedTab) {
                selectedTab.classList.add('tab-content-active');
            }
        });
    });
});
