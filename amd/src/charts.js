define(['core/chartjs'], function(Chart) {
    return {
        init: function(mbtiData, aspectData, strings) {
            // Debug de datos
            console.log('MBTI Data:', mbtiData);
            console.log('Aspect Data:', aspectData);
            console.log('Strings:', strings);
            
            // Paleta de colores SAVIO UTB
            const colorPalette = {
                mbti: [
                    '#005B9A', '#FF8200', '#FFB600', '#00B5E2', 
                    '#78BE20', '#2C5234', '#652C8F', '#91268F', 
                    '#D0006F', '#AA182C', '#8B0304', '#E35205', 
                    '#385CAD', '#0077C8', '#00263A', '#00A9B7'
                ],
                introversion: ['#005B9A', '#FF8200'],
                sensacion: ['#00B5E2', '#FFB600'],
                pensamiento: ['#78BE20', '#652C8F'],
                juicio: ['#AA182C', '#0077C8']
            };

            // Configuración común para todas las gráficas
            const commonOptions = {
                responsive: true,
                maintainAspectRatio: true,
                animation: {
                    duration: 1000,
                    easing: 'easeOutQuart'
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            boxWidth: 15,
                            padding: 10,
                            font: {
                                size: 11
                            }
                        }
                    },
                    title: {
                        display: true,
                        font: {
                            size: 14,
                            weight: '500'
                        },
                        padding: {
                            top: 5,
                            bottom: 15
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 43, 73, 0.8)',
                        titleFont: {
                            size: 12
                        },
                        bodyFont: {
                            size: 12
                        },
                        padding: 8,
                        cornerRadius: 4
                    }
                }
            };

            // Función para crear gráfico de pie MBTI
            function createMBTIChart() {
                var ctxPie = document.getElementById('mbtiChart');
                if (ctxPie) {
                    ctxPie = ctxPie.getContext('2d');
                    
                    // Filtrar tipos MBTI con valores > 0
                    const mbtiLabels = [];
                    const mbtiValues = [];
                    const mbtiColors = [];
                    
                    let colorIndex = 0;
                    // Asegurarse de que mbtiData sea un objeto
                    if (typeof mbtiData === 'string') {
                        try {
                            mbtiData = JSON.parse(mbtiData);
                        } catch (e) {
                            console.error('Error al analizar mbtiData:', e);
                        }
                    }
                    
                    Object.keys(mbtiData).forEach(key => {
                        if (mbtiData[key] > 0) {
                            mbtiLabels.push(key);
                            mbtiValues.push(mbtiData[key]);
                            mbtiColors.push(colorPalette.mbti[colorIndex % colorPalette.mbti.length]);
                            colorIndex++;
                        }
                    });
                    
                    // Si no hay datos, no mostrar ningún dato de ejemplo
                    if (mbtiLabels.length === 0) {
                        // No hay estudiantes que hayan realizado el test - no mostrar datos
                        document.getElementById('mbtiChart').parentNode.innerHTML = 
                            '<div class="alert alert-info text-center" style="margin-top: 20px;">' + 
                            strings.sin_datos_estudiantes + '</div>';
                        return;
                    }
                    
                    new Chart(ctxPie, {
                        type: 'pie',
                        data: {
                            labels: mbtiLabels,
                            datasets: [{
                                data: mbtiValues,
                                backgroundColor: mbtiColors,
                                borderColor: '#ffffff',
                                borderWidth: 1,
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: {
                                title: {
                                    display: true,
                                    text: strings.titulo_distribucion_mbti,
                                    font: {
                                        size: 14,
                                        weight: '500'
                                    },
                                    padding: {
                                        top: 5,
                                        bottom: 15
                                    }
                                },
                                legend: {
                                    position: 'top',
                                    labels: {
                                        boxWidth: 15,
                                        padding: 8,
                                        font: {
                                            size: 11
                                        }
                                    }
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 43, 73, 0.8)',
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.label || '';
                                            if (label) {
                                                label += ': ';
                                            }
                                            if (context.parsed !== null) {
                                                let total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                                let percentage = Math.round((context.parsed / total) * 100);
                                                label += context.parsed + ' (' + percentage + '%)';
                                            }
                                            return label;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            }

            // Función para crear gráficos de barras
            function createBarChart(elementId, title, labels, data, colors) {
                var ctx = document.getElementById(elementId);
                if (ctx) {
                    ctx = ctx.getContext('2d');
                    
                    console.log('Creating bar chart', elementId, title, labels, data, colors);
                    
                    // Verificar si tenemos datos, pero no usar datos de ejemplo si no hay
                    if (!data || !data.length || (data[0] === 0 && data[1] === 0)) {
                        // No hay estudiantes que hayan realizado el test - no mostrar datos
                        document.getElementById(elementId).parentNode.innerHTML = 
                            '<div class="alert alert-info text-center" style="margin-top: 10px;">' + 
                            strings.sin_datos_estudiantes + '</div>';
                        return;
                    }
                    
                    // Determinar el máximo valor para la escala Y
                    const maxValue = Math.max(...data);
                    const yMax = Math.ceil(maxValue * 1.1);
                    
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: strings.num_estudiantes_header,
                                data: data,
                                backgroundColor: colors,
                                borderColor: colors,
                                borderWidth: 0,
                                borderRadius: 2,
                                barPercentage: 0.8,
                                categoryPercentage: 0.9
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            indexAxis: 'x',
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: yMax,
                                    ticks: {
                                        stepSize: 1,
                                        precision: 0,
                                        font: {
                                            size: 11
                                        }
                                    },
                                    grid: {
                                        color: 'rgba(0, 43, 73, 0.05)',
                                        drawBorder: false
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false,
                                        drawBorder: false
                                    },
                                    ticks: {
                                        font: {
                                            size: 11
                                        }
                                    }
                                }
                            },
                            plugins: {
                                title: {
                                    display: true,
                                    text: title,
                                    font: {
                                        size: 14,
                                        weight: '500'
                                    },
                                    padding: {
                                        top: 5,
                                        bottom: 15
                                    }
                                },
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 43, 73, 0.8)',
                                    callbacks: {
                                        label: function(context) {
                                            return strings.num_estudiantes_header + ': ' + context.parsed.y;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            }

            // Crear todas las gráficas
            createMBTIChart();

            // Crear gráficas de barras para cada aspecto
            createBarChart(
                'generalTrendChart',
                strings.introversion_extroversion,
                [strings.Introvertido, strings.Extrovertido],
                [aspectData.Introvertido || 0, aspectData.Extrovertido || 0],
                colorPalette.introversion
            );

            createBarChart(
                'infoProcessingChart',
                strings.sensacion_intuicion,
                [strings.Sensing, strings.Intuicion],
                [aspectData.Sensing || 0, aspectData.Intuición || 0],
                colorPalette.sensacion
            );

            createBarChart(
                'decisionMakingChart',
                strings.pensamiento_sentimiento,
                [strings.Pensamiento, strings.Sentimiento],
                [aspectData.Pensamiento || 0, aspectData.Sentimiento || 0],
                colorPalette.pensamiento
            );

            createBarChart(
                'organizationChart',
                strings.juicio_percepcion,
                [strings.Juicio, strings.Percepcion],
                [aspectData.Juicio || 0, aspectData.Percepción || 0],
                colorPalette.juicio
            );
        }
    };
}); 