<%--
    ExpandableArrayList template.
    Located at: templates/Sunnysideup/ArrayToUl/View/ExpandableArrayList.ss

    No styling is emitted — every element carries a class for you to target
    in your own stylesheet (see the class reference in ExpandableArrayList.php).

    Two collapse mechanisms, both driven by the HTML `hidden` attribute (hidden
    by the browser's own UA stylesheet, so no author CSS needed) plus inline
    onclick handlers (so it keeps working when injected via AJAX / innerHTML):

      * NESTING:  nested lists start collapsed behind .eal-disclosure, so the
                  first view is the top level only.
      * OVERFLOW: lists longer than `collapseAfter` (default 25) show the first
                  N rows and a "Show X more" .eal-toggle button.

    Style the open state from aria-expanded="true" on either button.
    `$TypeClass` (eal-type-num, eal-type-bool, …) sits on the whole row.
--%>
<% if $IsRoot %><div class="eal"><% end_if %>

<div class="eal-section<% if $IsNested %> eal-nested<% end_if %>">

    <% if $IsNested %>
        <button type="button" class="eal-disclosure"
                aria-expanded="<% if $StartCollapsed %>false<% else %>true<% end_if %>"
                onclick="var b=this,x=b.getAttribute('aria-expanded')!=='true';b.setAttribute('aria-expanded',x);var n=b.nextElementSibling;while(n){n.hidden=!x;n=n.nextElementSibling;}">
            <span class="eal-disclosure-icon" aria-hidden="true"></span>
            <span class="eal-disclosure-label">$SummaryLabel</span>
        </button>
    <% end_if %>

    <% if $IsEmpty %>
        <span class="eal-empty-list"<% if $StartCollapsed %> hidden<% end_if %>>$EmptyLabel</span>
    <% else %>

        <% if $IsAssoc %>
            <dl class="eal-list eal-list--map"<% if $StartCollapsed %> hidden<% end_if %>>
                <% loop $Items %>
                <div class="eal-row $TypeClass<% if $IsCollapsible %> eal-collapsible<% end_if %>"<% if $IsHidden %> hidden<% end_if %>>
                    <dt class="eal-key">$Key</dt>
                    <dd class="eal-value">$Value</dd>
                </div>
                <% end_loop %>
            </dl>
        <% else %>
            <ul class="eal-list eal-list--seq"<% if $StartCollapsed %> hidden<% end_if %>>
                <% loop $Items %>
                <li class="eal-row $TypeClass<% if $IsCollapsible %> eal-collapsible<% end_if %>"<% if $IsHidden %> hidden<% end_if %>>
                    <div class="eal-value">$Value</div>
                </li>
                <% end_loop %>
            </ul>
        <% end_if %>

        <% if $NeedsCollapse %>
            <button type="button" class="eal-toggle"
                    aria-expanded="<% if $StartExpanded %>true<% else %>false<% end_if %>"
                    data-count="$HiddenCount"<% if $StartCollapsed %> hidden<% end_if %>
                    onclick="var b=this,s=b.parentNode,x=b.getAttribute('aria-expanded')!=='true';b.setAttribute('aria-expanded',x);var L=s.querySelector('.eal-list'),c=L?L.children:[];for(var i=0;i&lt;c.length;i++){if(c[i].classList.contains('eal-collapsible'))c[i].hidden=!x;}var t=b.querySelector('.eal-toggle-label');if(t)t.textContent=x?'Show less':('Show '+b.dataset.count+' more');">
                <span class="eal-toggle-icon" aria-hidden="true"></span>
                <span class="eal-toggle-label"><% if $StartExpanded %>Show less<% else %>Show {$HiddenCount} more<% end_if %></span>
            </button>
        <% end_if %>

    <% end_if %>

</div>
<% if $IsRoot %></div><% end_if %>
