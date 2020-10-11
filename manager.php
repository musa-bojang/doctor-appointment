<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8"/>
  <title>HTML5 Doctor Appointment Scheduling (JavaScript/PHP)</title>

  <link type="text/css" rel="stylesheet" href="css/layout.css"/>

  <!-- DayPilot library -->
  <script src="js/daypilot/daypilot-all.min.js"></script>
</head>
<body>
<?php require_once '_header.php'; ?>

<div class="main">
  <?php require_once '_navigation.php'; ?>

  <div>

    <div class="column-left">
      <div id="nav"></div>
    </div>
    <div class="column-main">

      <div class="toolbar">
                    <span class="toolbar-item">Scale:
<!--                        <label for='scale-15min'><input type="radio" value="15min" name="scale" id='scale-15min'> 15-Min</label>-->
                        <label for='scale-hours'><input type="radio" value="hours" name="scale" id='scale-hours'
                                                        checked> Hours</label>
                        <label for='scale-shifts'><input type="radio" value="shifts" name="scale" id='scale-shifts'> Shifts</label></span>
        <span class="toolbar-item"><label for="business-only"><input type="checkbox" id="business-only"> Hide non-business hours</label></span>
        <span class="toolbar-item">Slots: <button id="clear">Clear</button> Deletes all free slots this month</span>

      </div>

      <div id="scheduler"></div>
    </div>

  </div>
</div>

<script src="js/daypilot/daypilot-all.min.js"></script>

<script>
  var nav = new DayPilot.Navigator("nav");
  nav.selectMode = "month";
  nav.showMonths = 3;
  nav.skipMonths = 3;
  nav.onTimeRangeSelected = function (args) {
    if (scheduler.visibleStart().getDatePart() <= args.day && args.day < scheduler.visibleEnd()) {
      scheduler.scrollTo(args.day, "fast");  // just scroll
    } else {
      loadEvents(args.day);  // reload and scroll
    }
  };
  nav.init();

  var scheduler = new DayPilot.Scheduler("scheduler");
  scheduler.visible = false; // will be displayed after loading the resources
  scheduler.scale = "Manual";
  scheduler.timeline = getTimeline();
  scheduler.timeHeaders = getTimeHeaders();
  scheduler.useEventBoxes = "Never";
  scheduler.eventDeleteHandling = "Update";
  scheduler.eventClickHandling = "Disabled";
  scheduler.eventMoveHandling = "Disabled";
  scheduler.eventResizeHandling = "Disabled";
  scheduler.allowEventOverlap = false,
    scheduler.onBeforeTimeHeaderRender = function (args) {
      args.header.html = args.header.html.replace(" AM", "a").replace(" PM", "p");  // shorten the hour header
    };
  scheduler.onBeforeEventRender = function (args) {
    switch (args.data.tags.status) {
      case "free":
        args.data.backColor = "#3d85c6";  // blue
        args.data.barHidden = true;
        args.data.borderColor = "darker";
        args.data.fontColor = "white";
        args.data.deleteDisabled = document.querySelector("input[name=scale]:checked").value === "shifts"; // only allow deleting in the more detailed hour scale mode
        break;
      case "waiting":
        args.data.backColor = "#e69138";  // orange
        args.data.barHidden = true;
        args.data.borderColor = "darker";
        args.data.fontColor = "white";
        break;
      case "confirmed":
        args.data.backColor = "#6aa84f";  // green
        args.data.barHidden = true;
        args.data.borderColor = "darker";
        args.data.fontColor = "white";
        break;
    }

  };
  scheduler.onEventDeleted = function (args) {
    var params = {
      id: args.e.id(),
    };
    DayPilot.Http.ajax({
      url: "backend_delete.php",
      data: params,
      success: function (ajax) {
        scheduler.message("Deleted.");
      }
    })
  };

  scheduler.onTimeRangeSelected = function (args) {
    var dp = scheduler;
    var scale = document.querySelector("input[name=scale]:checked").value;

    var params = {
      start: args.start.toString(),
      end: args.end.toString(),
      resource: args.resource,
      scale: scale
    };

    DayPilot.Http.ajax({
      url: "backend_create.php",
      data: params,
      success: function (ajax) {
        loadEvents();
        dp.message(ajax.data.message);
      }
    });

    dp.clearSelection();

  };
  scheduler.init();


  loadResources();
  loadEvents(DayPilot.Date.today());

  function loadEvents(day) {
    var from = scheduler.visibleStart();
    var to = scheduler.visibleEnd();
    if (day) {
      from = new DayPilot.Date(day).firstDayOfMonth();
      to = from.addMonths(1);
    }

    var params = {
      start: from.toString(),
      end: to.toString()
    };

    DayPilot.Http.ajax({
      url: "backend_events.php",
      data: params,
      success: function (ajax) {
        var data = ajax.data;

        var options = {
          events: data
        };

        if (day) {
          options.timeline = getTimeline(day);
          options.scrollTo = day;
        }

        scheduler.update(options);

        nav.events.list = data;
        nav.update();
      }
    });

  }

  function loadResources() {
    DayPilot.Http.ajax({
      url: "backend_resources.php",
      success: function (ajax) {
        scheduler.resources = ajax.data;
        scheduler.visible = true;
        scheduler.update();
      }
    });
  }

  function getTimeline(date) {
    var date = date || DayPilot.Date.today();
    var start = new DayPilot.Date(date).firstDayOfMonth();
    var days = start.daysInMonth();
    var scale = document.querySelector("input[name=scale]:checked").value;
    var businessOnly = document.querySelector("#business-only").checked;

    var morningShiftStarts = 9;
    var morningShiftEnds = 13;
    var afternoonShiftStarts = 14;
    var afternoonShiftEnds = 18;

    if (!businessOnly) {
      morningShiftStarts = 0;
      morningShiftEnds = 12;
      afternoonShiftStarts = 12;
      afternoonShiftEnds = 24;
    }

    var timeline = [];

    var increaseMorning;  // in hours
    var increaseAfternoon;  // in hours
    switch (scale) {
      case "15min":
        increaseMorning = 0.25;
        increaseAfternoon = 0.25;
        break;
      case "hours":
        increaseMorning = 1;
        increaseAfternoon = 1;
        break;
      case "shifts":
        increaseMorning = morningShiftEnds - morningShiftStarts;
        increaseAfternoon = afternoonShiftEnds - afternoonShiftStarts;
        break;
      default:
        throw "Invalid scale value";
    }

    for (var i = 0; i < days; i++) {
      var day = start.addDays(i);

      for (var x = morningShiftStarts; x < morningShiftEnds; x += increaseMorning) {
        timeline.push({start: day.addHours(x), end: day.addHours(x + increaseMorning)});
      }
      for (var x = afternoonShiftStarts; x < afternoonShiftEnds; x += increaseAfternoon) {
        timeline.push({start: day.addHours(x), end: day.addHours(x + increaseAfternoon)});
      }
    }

    return timeline;
  }

  function getTimeHeaders() {
    var scale = document.querySelector('input[name=scale]:checked').value;
    switch (scale) {
      case "15min":
        return [{groupBy: "Month"}, {groupBy: "Day", format: "dddd d"}, {
          groupBy: "Hour",
          format: "h tt"
        }, {groupBy: "Cell", format: "m"}];
        break;
      case "hours":
        return [{groupBy: "Month"}, {groupBy: "Day", format: "dddd d"}, {groupBy: "Hour", format: "h tt"}];
        break;
      case "shifts":
        return [{groupBy: "Month"}, {groupBy: "Day", format: "dddd d"}, {groupBy: "Cell", format: "tt"}];
        break;
    }
  }


  document.querySelector("#business-only").addEventListener("click", function () {
    scheduler.timeline = getTimeline();
    scheduler.update();
  });

  var radios = Array.apply(null, document.querySelectorAll("input[name=scale]")).forEach(function(item) {
    item.addEventListener("change", function (ev) {
      scheduler.timeline = getTimeline();
      scheduler.timeHeaders = getTimeHeaders();
      scheduler.update();
    });
  });

  document.querySelector("#clear").addEventListener("click", function () {
    var dp = scheduler;
    var params = {
      start: dp.visibleStart(),
      end: dp.visibleEnd()
    };
    DayPilot.Http.ajax({
      url: "backend_clear.php",
      data: params,
      success: function (ajax) {
        dp.message(ajax.data.message);
        loadEvents();
      }
    })
  });

</script>

</body>
</html>
