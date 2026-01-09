define(['core/chartjs'], function(Chart) {
    return {
        init: function(chartId, labels, data, datasetLabel) {
            var el = document.getElementById(chartId);
            if(!el) return;
            var ctx = el.getContext('2d');
            
            return new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: datasetLabel,
                        data: data,
                        backgroundColor: 'rgba(0, 191, 145, 0.2)',
                        borderColor: 'rgba(0, 191, 145, 1)',
                        pointBackgroundColor: 'rgba(0, 191, 145, 1)',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgba(0, 191, 145, 1)'
                    }]
                },
                options: {
                    scales: {
                        r: {
                            beginAtZero: true,
                            min: 0,
                            max: 9,
                            angleLines: { 
                                display: true,
                                color: 'rgba(0, 0, 0, 0.1)'
                            },
                            grid: {
                                color: function(context) {
                                    if (context.tick.value % 2 === 1) {
                                        return 'transparent';
                                    }
                                    return 'rgba(0, 0, 0, 0.1)';
                                },
                                lineWidth: 1
                            },
                            ticks: {
                                stepSize: 1,
                                display: true,
                                backdropColor: 'transparent',
                                callback: function(value) {
                                    return value % 2 !== 0 ? value : '';
                                },
                                font: {
                                    size: 12
                                }
                            },  
                            pointLabels: {
                                font: {size: 14}
                            }
                        }
                    }
                }
            });
        }
    };
});
