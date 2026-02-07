'use strict';





$(function () {
    

    
    var legendColor = '#1e9ff2',
        chartColors = '#1e9ff2',
        labelColor = '#1e9ff2',
        borderColor = '#1e9ff2';
  console.log(player_id);

    $.get(APP_URL + '/backend/chart/test/'+player_id, function (data) {
        console.log('data',data);
        const areaChartEl = document.querySelector('#lineAreaChart');
        var options = {
            series: [{
            name: "sales",
            data: [{
              x: '2019/01/01',
              y: 400
            }, {
              x: '2019/04/01',
              y: 430
            }, {
              x: '2019/07/01',
              y: 448
            }, {
              x: '2019/10/01',
              y: 470
            }, {
              x: '2020/01/01',
              y: 540
            }, {
              x: '2020/04/01',
              y: 580
            }, {
              x: '2020/07/01',
              y: 690
            }, {
              x: '2020/10/01',
              y: 690
            }]
          }],
            chart: {
            type: 'bar',
            height: 380
          },
          xaxis: {
            type: 'category',
            labels: {
              formatter: function(val) {
                return "Q" + dayjs(val).quarter()
              }
            },
            group: {
              style: {
                fontSize: '10px',
                fontWeight: 700
              },
              groups: [
                { title: '2019', cols: 4 },
                { title: '2020', cols: 4 }
              ]
            }
          },
          title: {
              text: 'Grouped Labels on the X-axis',
          },
          tooltip: {
            x: {
              formatter: function(val) {
                return "Q" + dayjs(val).quarter() + " " + dayjs(val).format("YYYY")
              }  
            }
          },
          };
  
          var chart = new ApexCharts(areaChartEl, options);
          chart.render();
    }).fail(function(error){
        console.log('error',error);
    })

    $.get(APP_URL + '/backend/chart/physical/'+player_id, function (data) {
        console.log(data);
        const areaChartEl = document.querySelector('#physicalChart');
        var options = {
            series: [{
            name: player,
            data: data.x,
          }],
            chart: {
            height: 750,
            type: 'radar',
          },
          title: {
            text: 'Average Physical evaluations'
          },
          xaxis: {
            categories: data.y
          }
          };




        if (typeof areaChartEl !== undefined && areaChartEl !== null) {
            const areaChart = new ApexCharts(areaChartEl, options);
            areaChart.render();
      
        }
    }).fail(function(error){
        console.log('error',error);
    })
})