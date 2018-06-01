if (!Object.assign) {
  Object.defineProperty(Object, 'assign', {
    enumerable: false,
    configurable: true,
    writable: true,
    value: function(target, firstSource) {
      'use strict';
      if (target === undefined || target === null) {
        throw new TypeError('Cannot convert first argument to object');
      }

      var to = Object(target);
      for (var i = 1; i < arguments.length; i++) {
        var nextSource = arguments[i];
        if (nextSource === undefined || nextSource === null) {
          continue;
        }

        var keysArray = Object.keys(Object(nextSource));
        for (var nextIndex = 0, len = keysArray.length; nextIndex < len; nextIndex++) {
          var nextKey = keysArray[nextIndex];
          var desc = Object.getOwnPropertyDescriptor(nextSource, nextKey);
          if (desc !== undefined && desc.enumerable) {
            to[nextKey] = nextSource[nextKey];
          }
        }
      }
      return to;
    }
  });
}

// 对Date的扩展，将 Date 转化为指定格式的String
// 月(M)、日(d)、小时(h)、分(m)、秒(s)、季度(q) 可以用 1-2 个占位符， 
// 年(y)可以用 1-4 个占位符，毫秒(S)只能用 1 个占位符(是 1-3 位的数字) 
// 例子： 
// (new Date()).Format("yyyy-MM-dd hh:mm:ss.S") ==> 2006-07-02 08:09:04.423 
// (new Date()).Format("yyyy-M-d h:m:s.S")      ==> 2006-7-2 8:9:4.18 
Date.prototype.Format = function (fmt) { //author: meizz 
    var o = {
        "M+": this.getMonth() + 1, //月份 
        "d+": this.getDate(), //日 
        "h+": this.getHours(), //小时 
        "m+": this.getMinutes(), //分 
        "s+": this.getSeconds(), //秒 
        "q+": Math.floor((this.getMonth() + 3) / 3), //季度 
        "S": this.getMilliseconds() //毫秒 
    };
    if (/(y+)/.test(fmt)) fmt = fmt.replace(RegExp.$1, (this.getFullYear() + "").substr(4 - RegExp.$1.length));
    for (var k in o)
    if (new RegExp("(" + k + ")").test(fmt)) fmt = fmt.replace(RegExp.$1, (RegExp.$1.length == 1) ? (o[k]) : (("00" + o[k]).substr(("" + o[k]).length)));
    return fmt;
};

Array.prototype.max = function()
{
    return Math.max.apply({},this);
};
Array.prototype.min = function()
{
    return Math.min.apply({},this);
};

/**
* 图表控制器
*/      
var ChartControl = {
    kChart:null,//K线图表对象
    areaChart:null,//面积图图表对象
    chartType:0,//图表间隔
    chartInterval:1,//间隔时间
    chartRows:1391,//获取数据行数
    fullDate:[],//完整X轴刻度日期  
    max:0, //最大值
    min:0, //最小值
    average:0,//平均值
    speed:3000,//间隔2秒钟获取新数据点
    maxTime:"",//用于记录当前数据的最大日期
    chartData:[],//图表数据
    initUrl:"",//初始化页面的URL
    realTimeUrl:"",//实时获取数据的URL,
    ma5:[],//5日均线数据
    ma10:[],//10日均线数据
    ma30:[],//30日均线数据
    accSub: function(e, t) {
        var n, r, i, a;
        try {
            n = e.toString().split(".")[1].length
        } catch(o) {
            n = 0
        }
        try {
            r = t.toString().split(".")[1].length
        } catch(o) {
            r = 0
        }
        return i = Math.pow(10, Math.max(n, r)),
        a = n >= r ? n: r,
        ((e * i - t * i) / i).toFixed(a)
    },
    accMul: function(e, t) {
        var n = 0,
        r = e.toString(),
        i = t.toString();
        try {
            n += r.split(".")[1].length
        } catch(a) {}
        try {
            n += i.split(".")[1].length
        } catch(a) {}
        return Number(r.replace(".", "")) * Number(i.replace(".", "")) / Math.pow(10, n)
    },
    accDiv: function(e, t) {
        var n, r, i = 0,
        a = 0;
        try {
            i = e.toString().split(".")[1].length
        } catch(o) {}
        try {
            a = t.toString().split(".")[1].length
        } catch(o) {}
        return n = Number(e.toString().replace(".", "")),
        r = Number(t.toString().replace(".", "")),
        n / r * Math.pow(10, a - i)
    },
    accAdd: function(e, t) {
        var n, r, i;
        try {
            n = e.toString().split(".")[1].length
        } catch(a) {
            n = 0
        }
        try {
            r = t.toString().split(".")[1].length
        } catch(a) {
            r = 0
        }
        return i = Math.pow(10, Math.max(n, r)),
        (e * i + t * i) / i
    },  
    /**
    * 计算前interval的平均收盘价数据
    **/
    GetAvgData:function(curIndex, interval, data) {
        var avgData = 0;
        var reallyCount = 1;
        for (var i = curIndex; i >= 0 && (curIndex - i) < interval; i--) {
            avgData += data[i][4]; //收盘价数据
            reallyCount++;
        }
        //console.log(reallyCount);
        if(reallyCount == 1)
        {
            console.log(curIndex+"行数据有问题");
        }else
            //取平均收盘价
            avgData = Number((avgData / reallyCount).toFixed(2));
        return avgData;
    },
    /**
    * 通过时间差距得到的平均线数组
    * @param interval(integer) 日期差
    * @param data(object) 日K数据对象 
    **/
    GetSeriesDataByDateRange:function(interval, data) {
        var seriesData = [];
        for (var i = 0; i < data.length; i++) {
            seriesData.push(this.GetAvgData(i, interval, data));
        }
        return seriesData;
    },
    init:function(){

        //获取数据              
        var inThis = this;
        //图表下方K线图类型切换事件
        $(".prok li").unbind();
        $(".prok li").bind("click",function(){
            ChartControl.chartType = $(this).data("type");
            ChartControl.chartInterval = $(this).data("interval");
            ChartControl.chartRows = $(this).data("rows");
            //设置数据获取间隔时间
            if(ChartControl.chartType == "area")
                ChartControl.speed = 3000;
            else
                ChartControl.speed = ChartControl.chartInterval*60*1000;
            
            $(".ul_four li a").removeClass("shows");
            $(this).addClass("shows");
            inThis.maxTime = "";
            inThis.chartData = [];
            inThis.fullDate = [];
            inThis.createChart(inThis.formatterData(inThis.getKChartData(inThis.chartType)));
        });
        $(".ul_four li a").eq(0).trigger("click");
    },
    /**
    * 获取K线图数据
    * @param _type:string 图表数据类型{area:分时图；5fen：5分钟K线；30fen：30分钟K线；60fen：60分K线；ri：日K线}
    */
    getKChartData:function(_type){
        var _data = [];
        
        //这里根据类型不一样通过ajax获取不一样的图表数据
        var _url = this.initUrl+"&interval="+ChartControl.chartInterval;

        $.ajax({
            url:_url,
            type:"get",
            dataType:"json",
            async:false,//同步
            success:function(data){
                if(data)
                    _data = data;
            }
        });
        
        this.chartData = _data;
        return _data;
    },
    /**
    * 实时获取图表数据
    */
    onRealTime:function(){

        var data = null;
        $.ajax({
            url:ChartControl.realTimeUrl,
            type:"get",
            dataType:"json",
            async:false,//同步
            success:function(result){
                if(result)

                    data = result;
                    ChartControl.chartData.push(data[0]);
            }
        });
        if(data && data.length > 0)
        {    
            //判断是否与前一个数据点日期一样 一样则直接跳过
            if(data[0].time != ChartControl.chartData[ChartControl.chartData.length-2].time)
            {
                ChartControl.maxTime = ChartControl.fullDate[ChartControl.fullDate.length-1];
                if(ChartControl.chartType == "area")
                {
                    ChartControl.chartData.push(data[0]);
                    //追加数据点
                    ChartControl.fullDate.push(data[0].fulltime);
                    var option = ChartControl.areaChart.getOption();
                    var xLabel = new Date(data[0].time*1).Format("hh:mm");
                    //保证相同刻度不要显示
                    //if(option.xAxis[0].data.indexOf(xLabel) == -1)
                        option.xAxis[0].data.push(xLabel);
                    option.series[0].data.push(data[0].price*1);
                    //这里做控制 显示多少个数据点
                    var maxPoint = 50;
                    if(option.xAxis[0].data.length > maxPoint)
                    {
                        //删除前面第一个数据点
                        option.xAxis[0].data.shift();
                        option.series[0].data.shift();
                    }
                    ChartControl.areaChart.setOption(option);
                }
                else
                {
                    ChartControl.chartData.push([data[0].time*1,data[0].open*1,data[0].highest*1,data[0].lowest*1,data[0].close*1]); 
                    //追加数据点
                    ChartControl.fullDate.push(data[0].fulltime);
                    var option = ChartControl.kChart.getOption();
                    var xLabel = new Date(data[0].time*1).Format("hh:mm");
                    //保证相同刻度不要显示
                    //if(option.xAxis[0].data.indexOf(xLabel) == -1)
                        option.xAxis[0].data.push(xLabel);
                    option.series[0].data.push([data[0].open*1,data[0].close*1,data[0].lowest*1,data[0].highest*1]);
                    //这里做控制 显示多少个数据点
                    
                    
                    if(option.xAxis[0].data.length > 10)
                    {
                        //删除前面第一个数据点
                        option.xAxis[0].data.shift();
                        option.series[0].data.shift();
                    }
                    
                    try
                    {
                        ChartControl.kChart.setOption(option);     
                    }catch(e){}      
                }
            }            
        }
        setTimeout(function(){
            ChartControl.onRealTime();
        },ChartControl.speed);
    },
    /**
    * 格式化图表数据
    * @param _data:array 图表数据数组
    */
    formatterData:function(_data)
    {
        var categoryData = [];
        var  kData = [],areaData = [];
        for (var i = 0; i < _data.length; i++) {
            this.fullDate.push(_data[i].fulltime);
            categoryData.push(_data[i].time*1);
            //开收低高
            kData.push([_data[i].open*1,_data[i].close*1,_data[i].lowest*1,_data[i].highest*1]);
            areaData.push(_data[i].price*1);
        }
        this.max = areaData.max();
        this.min = areaData.min();
        this.average = parseInt((this.max+this.min)/2);  
        this.maxTime = this.fullDate[this.fullDate.length-1];          
        return {
            categoryData: categoryData,
            kData: kData, //k线需要的数据
            areaData:areaData //面积图需要的价格数据
        };
    },
    /**
    * 格式化分时图X轴刻度
    * @param _categoryData:array 刻度数组
    */
    formatterareaXLabel:function(_categoryData)
    {
        var _newData = [];
        for(var i = 0;i<_categoryData.length;i++)
        {
            _newData.push(new Date(_categoryData[i]).Format("hh:mm"));
        }
        return _newData;
    },  
    /**
    * 获取图表option
    */
    getOption: function(e) {
        var t = {
            title: {},
            tooltip: {
                trigger: "axis",
                textStyle: {
                    fontSize: 28
                },
                extraCssText: "z-index: 100"
            },
            legend: {
                show: !1,
                data:[]
            },
            toolbox: {
                show: !1
            },
            grid: {
                top: "8%",
                left: "15%",
                right: "15%",
                bottom: "10%",
                borderColor: "#000",
                borderWidth: 1
            },
            dataZoom: {
                show: false,
                start: 0,
                end: 100
            },
            xAxis: [{
                type: "category",
                data: [0],
                boundaryGap: !1,
                axisTick: {
                    show: !1
                },
                axisLine: {
                    show: !0,
                    lineStyle: {
                        width: 1,
                        color: "#E5E7EE"
                    }
                },
                axisLabel: {
                    interval: 239,
                    textStyle: {
                        color: "#9D9D9D"
                    }
                },
                splitLine: {
                    show: false,
                    lineStyle: {
                        width: 1,
                        color: "#E5E7EE"
                    }
                }
            }],
            yAxis: [{
                scale: true,
                axisLine: {
                    show: !0,
                    lineStyle: {
                        width: 1,
                        color: "#E5E7EE"
                    }
                },
                axisTick: {
                    show: !1
                },
                axisLabel: {
                    textStyle: {
                        color: "#9B9B9B"
                    }
                },
                splitLine: {
                    show: !0,
                    lineStyle: {
                        width: 1,
                        color: "#E5E7EE"
                    }
                },
                splitArea: {
                    show: !1
                }
            },
            {}],
            series: [{
                name: "最新价",
                type: "line",
                data: [0],
                lineStyle: {
                    normal: {
                        color: "#8291F1",
                        width: 1
                    }
                }
            }],
            textStyle: {
                fontSize: 24
            }
        };
        return t
    },
    /**
    * 根据数据集合生成K线图
    */
    createChart:function(_dataConfig){   

        if(ChartControl.chartType == "area")                        
            ChartControl.createAreaChart(_dataConfig);  
        else
            ChartControl.createKChrt(_dataConfig);  
    },
    /**
    * 生成K线图
    */

	createKChrt:function(_dataConfig)
	{
		var _start = 0;

		if(_dataConfig.categoryData.length >500)
			_start = 50;
	    else if(_dataConfig.categoryData.length > 1000)
	    	_start = 60;
		var option = this.getOption();
		option.grid.right = "5%",
        option.xAxis[0].data = this.formatterareaXLabel(_dataConfig.categoryData),
        option.xAxis[0].axisLabel.interval = 5,
        option.yAxis[0].splitLine.lineStyle.width = 1,
        option.yAxis[0].axisLabel.textStyle.color = "#9B9B9B",
        option.yAxis[1] = null;
        option.series[0].data = _dataConfig.kData,
        option.series[0].type = "candlestick",
        option.series[0].itemStyle = {
            normal: {
                color: "#F91752",
                color0: "#14F49B",
                borderColor: "#F91752",
                borderColor0: "#14F49B"
            }
        };

        
        option.tooltip = {
            trigger: "axis",
            formatter: function(e) {
                var t = ChartControl.fullDate[e[0].dataIndex];
                        t += "<br/>  开盘 : " + e[0].value[0] + "<br/>  最高 : " + e[0].value[3],
                        t += "<br/>  最低 : " + e[0].value[2] + "<br/>  收盘 : " + e[0].value[1];
                        
                        return t;
            },
            extraCssText: "z-index: 100"
        }
        ChartControl.kChart = echarts.init(document.getElementById("myKChart"));                     
        ChartControl.kChart.setOption(option, true);

        //间隔时长获取新数据点
        setTimeout(function(){
            if(ChartControl.kChart && ChartControl.chartType != "area")
                ChartControl.onRealTime();
        },ChartControl.speed);
    },
    /**
    * 生成面积图
    */
    createAreaChart:function(_dataConfig)
    {
        var _start = 0;
        if(_dataConfig.categoryData.length >500)
            _start = 50;
        else if(_dataConfig.categoryData.length > 1000)
            _start = 60;
        var option = this.getOption();
        option.grid.right = "15%",
        option.xAxis[0].data = this.formatterareaXLabel(_dataConfig.categoryData);
        option.xAxis[0].axisLabel.interval = parseInt(option.xAxis[0].data.length/7);
        option.yAxis[0].splitLine.lineStyle.width = 1;
        option.yAxis[0].axisLabel.textStyle.color = function(e) {             
            var t = ChartControl.average;       
            var n = e ? e.replace(/,/g, "")*1 : 0;
            return n > t ? "#F67363": n == t ? "#000": "#44BA56"
        };        
        option.yAxis[0].scale = true;
        option.yAxis[1] = Object.assign({},
        option.yAxis[0], {
            min: ChartControl.min,
            max: ChartControl.max,
            splitLine: {
                show: !1
            },
            axisLabel: {
                show: !0,
                inside: !1,
                formatter: function(e) {                    
                    return e = ChartControl.accDiv(ChartControl.accMul(ChartControl.accSub(e, ChartControl.average), 100), ChartControl.average).toFixed(2),
                    e + "%";
                },
                textStyle: {
                    color: function(e) {
                        var t = ChartControl.average; 
                        var n = e ? e.replace(/,/g, "")*1 : 0;
                        return n > t ? "#F67363": n == t ? "#000": "#44BA56";
                    }
                }
            }
        });
        option.series[0].data = _dataConfig.areaData;
        option.tooltip = {
                        trigger: "axis",
                        formatter: function(e) {
                            if ("-" == e[0].value) return "";
                            var t = ChartControl.fullDate[e[0].dataIndex];
                            return t += "<br/>  最新价 : " + e[0].value
                        },
                        extraCssText: "z-index: 100"
                    };
        ChartControl.areaChart = echarts.init(document.getElementById("myKChart"));                      
        ChartControl.areaChart.setOption(option, true);

        //间隔时长获取新数据点
        setTimeout(function(){
            if(ChartControl.areaChart && ChartControl.chartType == "area")
                ChartControl.onRealTime();
        },ChartControl.speed);
    }
};